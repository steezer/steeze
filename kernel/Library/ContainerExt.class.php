<?php

namespace Library;

use Closure;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionParameter;
use Library\Exception;
use Contract\Container as ContainerContract;

class ContainerExt implements ArrayAccess, ContainerContract{
	
	protected static $instance; //当前全局可用的容器
	protected $resolved=[]; //已经解决的类型的数组
	protected $bindings=[]; //容器绑定
	protected $methodBindings=[]; //容器的方法绑定
	protected $instances=[]; //容器中的共享实例
	protected $aliases=[]; //容器的别名
	protected $abstractAliases=[]; //由抽象名称关联的注册别名
	protected $extenders=[]; //扩展闭包服务
	protected $tags=[]; //所有注册的标签
	protected $buildStack=[]; //当前已经构建完成的实例栈
	protected $with=[]; //参数栈
	public $contextual=[]; //上下文绑定映射
	protected $reboundCallbacks=[]; //所有注册的反弹回调
	protected $globalResolvingCallbacks=[]; //所有全局解析回调
	protected $globalAfterResolvingCallbacks=[]; //解决回调后的所有全局
	protected $resolvingCallbacks=[]; //类类型的所有解析回调
	protected $afterResolvingCallbacks=[]; //通过类类型解析回调函数

	/**
	 * 确定给定的抽象类型是否已被绑定
	 *
	 * @param string $abstract
	 * @return bool
	 */
	public function bound($abstract){
		return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	public function has($id){
		return $this->bound($id);
	}

	/**
	 * 确定给定的抽象类型是否已被解析
	 *
	 * @param string $abstract
	 * @return bool
	 */
	public function resolved($abstract){
		if($this->isAlias($abstract)){
			$abstract=$this->getAlias($abstract);
		}
		return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
	}

	/**
	 * 判断给定的类型是否是单例模式
	 *
	 * @param string $abstract
	 * @return bool
	 */
	public function isShared($abstract){
		return isset($this->instances[$abstract]) || (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true);
	}

	/**
	 * 判断是否为别名
	 *
	 * @param string $name
	 * @return bool
	 */
	public function isAlias($name){
		return isset($this->aliases[$name]);
	}

	/**
	 * 向容器注册一个绑定
	 *
	 * @param string|array $abstract
	 * @param Closure|string|null $concrete
	 * @param bool $shared
	 * @return void
	 */
	public function bind($abstract,$concrete=null,$shared=false){
		//删除已经绑定的实例
		$this->dropStaleInstances($abstract);
		
		if(is_null($concrete)){
			$concrete=$abstract;
		}
		
		//如果构建工厂不是Closure类型，则创建一个闭包对象
		if(!$concrete instanceof Closure){
			$concrete=$this->getClosure($abstract, $concrete);
		}
		
		$this->bindings[$abstract]=compact('concrete', 'shared');
		
		//更新依赖绑定
		if($this->resolved($abstract)){
			$this->rebound($abstract);
		}
	}

	/**
	 * 在构建类型时要使用闭包
	 *
	 * @param string $abstract
	 * @param string $concrete
	 * @return Closure
	 */
	protected function getClosure($abstract,$concrete){
		return function ($container,$parameters=[]) use ($abstract,$concrete){
			if($abstract == $concrete){
				return $container->build($concrete);
			}
			
			return $container->make($concrete, $parameters);
		};
	}

	/**
	 * 确定容器是否有一个方法绑定
	 *
	 * @param string $method
	 * @return bool
	 */
	public function hasMethodBinding($method){
		return isset($this->methodBindings[$method]);
	}

	/**
	 * 用Container::call绑定一个回调函数
	 *
	 * @param string $method
	 * @param Closure $callback
	 * @return void
	 */
	public function bindMethod($method,$callback){
		$this->methodBindings[$method]=$callback;
	}

	/**
	 * 获取给定方法的方法绑定
	 *
	 * @param string $method
	 * @param mixed $instance
	 * @return mixed
	 */
	public function callMethodBinding($method,$instance){
		return call_user_func($this->methodBindings[$method], $instance, $this);
	}

	/**
	 * Add a contextual binding to the container.
	 *
	 * @param string $concrete
	 * @param string $abstract
	 * @param Closure|string $implementation
	 * @return void
	 */
	public function addContextualBinding($concrete,$abstract,$implementation){
		$this->contextual[$concrete][$this->getAlias($abstract)]=$implementation;
	}

	/**
	 * Register a binding if it hasn't already been registered.
	 *
	 * @param string $abstract
	 * @param Closure|string|null $concrete
	 * @param bool $shared
	 * @return void
	 */
	public function bindIf($abstract,$concrete=null,$shared=false){
		if(!$this->bound($abstract)){
			$this->bind($abstract, $concrete, $shared);
		}
	}

	/**
	 * Register a shared binding in the container.
	 *
	 * @param string|array $abstract
	 * @param Closure|string|null $concrete
	 * @return void
	 */
	public function singleton($abstract,$concrete=null){
		$this->bind($abstract, $concrete, true);
	}

	/**
	 * "Extend" an abstract type in the container.
	 *
	 * @param string $abstract
	 * @param Closure $closure
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	public function extend($abstract,Closure $closure){
		$abstract=$this->getAlias($abstract);
		
		if(isset($this->instances[$abstract])){
			$this->instances[$abstract]=$closure($this->instances[$abstract], $this);
			
			$this->rebound($abstract);
		}else{
			$this->extenders[$abstract][]=$closure;
			
			if($this->resolved($abstract)){
				$this->rebound($abstract);
			}
		}
	}

	/**
	 * Register an existing instance as shared in the container.
	 *
	 * @param string $abstract
	 * @param mixed $instance
	 * @return mixed
	 */
	public function instance($abstract,$instance){
		$this->removeAbstractAlias($abstract);
		
		$isBound=$this->bound($abstract);
		
		unset($this->aliases[$abstract]);
		
		// We'll check to determine if this type has been bound before, and if it has
		// we will fire the rebound callbacks registered with the container and it
		// can be updated with consuming classes that have gotten resolved here.
		$this->instances[$abstract]=$instance;
		
		if($isBound){
			$this->rebound($abstract);
		}
		
		return $instance;
	}

	/**
	 * Remove an alias from the contextual binding alias cache.
	 *
	 * @param string $searched
	 * @return void
	 */
	protected function removeAbstractAlias($searched){
		if(!isset($this->aliases[$searched])){
			return;
		}
		
		foreach($this->abstractAliases as $abstract=>$aliases){
			foreach($aliases as $index=>$alias){
				if($alias == $searched){
					unset($this->abstractAliases[$abstract][$index]);
				}
			}
		}
	}

	/**
	 * Assign a set of tags to a given binding.
	 *
	 * @param array|string $abstracts
	 * @param array|mixed ...$tags
	 * @return void
	 */
	public function tag($abstracts,$tags){
		$tags=is_array($tags) ? $tags : array_slice(func_get_args(), 1);
		
		foreach($tags as $tag){
			if(!isset($this->tags[$tag])){
				$this->tags[$tag]=[];
			}
			
			foreach((array)$abstracts as $abstract){
				$this->tags[$tag][]=$abstract;
			}
		}
	}

	/**
	 * Resolve all of the bindings for a given tag.
	 *
	 * @param string $tag
	 * @return array
	 */
	public function tagged($tag){
		$results=[];
		
		if(isset($this->tags[$tag])){
			foreach($this->tags[$tag] as $abstract){
				$results[]=$this->make($abstract);
			}
		}
		
		return $results;
	}

	/**
	 * Alias a type to a different name.
	 *
	 * @param string $abstract
	 * @param string $alias
	 * @return void
	 */
	public function alias($abstract,$alias){
		$this->aliases[$alias]=$abstract;
		
		$this->abstractAliases[$abstract][]=$alias;
	}

	/**
	 * Bind a new callback to an abstract's rebind event.
	 *
	 * @param string $abstract
	 * @param Closure $callback
	 * @return mixed
	 */
	public function rebinding($abstract,Closure $callback){
		$this->reboundCallbacks[$abstract=$this->getAlias($abstract)][]=$callback;
		
		if($this->bound($abstract)){
			return $this->make($abstract);
		}
	}

	/**
	 * Refresh an instance on the given target and method.
	 *
	 * @param string $abstract
	 * @param mixed $target
	 * @param string $method
	 * @return mixed
	 */
	public function refresh($abstract,$target,$method){
		return $this->rebinding($abstract, function ($app,$instance) use ($target,$method){
			$target->{$method}($instance);
		});
	}

	/**
	 * Fire the "rebound" callbacks for the given abstract type.
	 *
	 * @param string $abstract
	 * @return void
	 */
	protected function rebound($abstract){
		$instance=$this->make($abstract);
		
		foreach($this->getReboundCallbacks($abstract) as $callback){
			call_user_func($callback, $this, $instance);
		}
	}

	/**
	 * Get the rebound callbacks for a given type.
	 *
	 * @param string $abstract
	 * @return array
	 */
	protected function getReboundCallbacks($abstract){
		if(isset($this->reboundCallbacks[$abstract])){
			return $this->reboundCallbacks[$abstract];
		}
		
		return [];
	}

	/**
	 * Wrap the given closure such that its dependencies will be injected when executed.
	 *
	 * @param Closure $callback
	 * @param array $parameters
	 * @return Closure
	 */
	public function wrap(Closure $callback,array $parameters=[]){
		return function () use ($callback,$parameters){
			return $this->call($callback, $parameters);
		};
	}

	/**
	 * Call the given Closure / class@method and inject its dependencies.
	 *
	 * @param callable|string $callback
	 * @param array $parameters
	 * @param string|null $defaultMethod
	 * @return mixed
	 */
	public function call($callback,array $parameters=[],$defaultMethod=null){
		return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
	}

	/**
	 * Get a closure to resolve the given type from the container.
	 *
	 * @param string $abstract
	 * @return Closure
	 */
	public function factory($abstract){
		return function () use ($abstract){
			return $this->make($abstract);
		};
	}

	/**
	 * 从容器中解析给定的类型
	 *
	 * @param string $abstract
	 * @param array $parameters
	 * @return mixed
	 */
	public function make($abstract,array $parameters=[]){
		return $this->resolve($abstract, $parameters);
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	public function get($id){
		if($this->has($id)){
			return $this->resolve($id);
		}
		
		throw new EntryNotFoundException();
	}

	/**
	 * 从容器中解析给定的类型.
	 *
	 * @param string $abstract
	 * @param array $parameters
	 * @return mixed
	 */
	protected function resolve($abstract,$parameters=[]){
		$abstract=$this->getAlias($abstract);
		$needsContextualBuild=!empty($parameters) || !is_null($this->getContextualConcrete($abstract));

		/**
		 * 如果类型的一个实例是目前管理作为一个单例,我们就返回一个现有的实例,
		 * 而不是实例化新的实例, 所以开发人员可以保持每次都使用相同的对象实例。
		 */
		if(isset($this->instances[$abstract]) && !$needsContextualBuild){
			return $this->instances[$abstract];
		}
		
		$this->with[]=$parameters;
		
		$concrete=$this->getConcrete($abstract);
		
		/*
		 * 我们准备实例化一个为绑定注册的具体类型的实例。这将实例化类型， 
		 * 并递归地解决它的任何“嵌套”依赖关系，直到所有问题都得到解决。
		 */
		if($this->isBuildable($concrete, $abstract)){
			$object=$this->build($concrete);
		}else{
			$object=$this->make($concrete);
		}
		
		/**
		 * 如果定义任何扩展的类型,则获取扩展,并将其应用到对象。 
		 * 通过这种扩展，我们可以更改配置或对对象进行修饰。
		 */
		foreach($this->getExtenders($abstract) as $extender){
			$object=$extender($object, $this);
		}
		
		/**
		 * 如果请求的类型被注册为单例对象，那么我们将希望在“内存”中缓存实例， 
		 * 这样我们就可以在以后的请求中返回它，而不需要在随后的请求中创建一个对象的全新实例
		 */
		if($this->isShared($abstract) && !$needsContextualBuild){
			$this->instances[$abstract]=$object;
		}
		
		$this->fireResolvingCallbacks($abstract, $object);
		
		/**
		 * 在返回之前，我们还将把已解析的标志设置为“true”，并弹出该构建的参数。
		 */
		$this->resolved[$abstract]=true;
		array_pop($this->with);
		
		return $object;
	}

	/**
	 * 根据抽象类获取实例
	 *
	 * @param string $abstract
	 * @return mixed $concrete
	 */
	protected function getConcrete($abstract){
		if(!is_null($concrete=$this->getContextualConcrete($abstract))){
			return $concrete;
		}

		if(isset($this->bindings[$abstract])){
			return $this->bindings[$abstract]['concrete'];
		}
		
		return $abstract;
	}

	/**
	 * 为给定的抽象获取上下文的具体绑定
	 *
	 * @param string $abstract
	 * @return string|null
	 */
	protected function getContextualConcrete($abstract){
		if(!is_null($binding=$this->findInContextualBindings($abstract))){
			return $binding;
		}
		
		if(empty($this->abstractAliases[$abstract])){
			return;
		}
		
		foreach($this->abstractAliases[$abstract] as $alias){
			if(!is_null($binding=$this->findInContextualBindings($alias))){
				return $binding;
			}
		}
	}

	/**
	 * 在上下文绑定数组中找到给定抽象的具体绑定
	 *
	 * @param string $abstract
	 * @return string|null
	 */
	protected function findInContextualBindings($abstract){
		if(isset($this->contextual[end($this->buildStack)][$abstract])){
			return $this->contextual[end($this->buildStack)][$abstract];
		}
	}

	/**
	 * 判断给定的实例是否可以构建.
	 *
	 * @param mixed $concrete
	 * @param string $abstract
	 * @return bool
	 */
	protected function isBuildable($concrete,$abstract){
		return $concrete === $abstract || $concrete instanceof Closure;
	}

	/**
	 * 实例化给定类型的具体实例
	 *
	 * @param string $concrete
	 * @return mixed
	 *
	 * @throws Library\Exception
	 */
	public function build($concrete){
		
		if($concrete instanceof Closure){
			return $concrete($this, $this->getLastParameterOverride());
		}
		
		$reflector=new ReflectionClass($concrete);
		
		/*
		 * 如果类型不能实例化，开发人员将尝试处理此类异常，
		 * 比如抽象类的接口没有对抽象进行绑定，因此我们需要对其进行处理。
		 * */
		
		if(!$reflector->isInstantiable()){
			return $this->notInstantiable($concrete);
		}
		
		$this->buildStack[]=$concrete;
		
		$constructor=$reflector->getConstructor();
		
		//没有构造函数的构建
		if(is_null($constructor)){
			array_pop($this->buildStack);
			return new $concrete();
		}
		
		$dependencies=$constructor->getParameters();
		
		/*
		 * 一旦我们有了所有构造函数的参数我们就可以创建每个参数依赖实例，
		 * 然后使用反射实例来创建一个这个类的新实例，注入所创建的依赖项
		 * */
		$instances=$this->resolveDependencies($dependencies);
		
		array_pop($this->buildStack);
		return $reflector->newInstanceArgs($instances);
	}

	/**
	 * 从反射参数中解决所有的依赖项
	 *
	 * @param array $dependencies
	 * @return array
	 */
	protected function resolveDependencies(array $dependencies){
		$results=[];
		
		foreach($dependencies as $dependency){
			/*
			 * 如果这个依赖对这个特殊的构建有一个覆盖，那么我们将使用它作为值。
			 * 否则，我们将继续执行这一系列的决议，并让反射尝试确定结果
			 * */
			if($this->hasParameterOverride($dependency)){
				$results[]=$this->getParameterOverride($dependency);
				
				continue;
			}
			
			/*
			 * 如果类是空的，它意味着依赖是一个字符串或其他的原始类型，
			 * 我们不能解决这个问题，因为它不是类，我们会用错误的方式进行处理，因为我们没有地方可以去。
			 * */
			$results[]=is_null($dependency->getClass()) ? $this->resolvePrimitive($dependency) : $this->resolveClass($dependency);
		}
		
		return $results;
	}

	/**
	 * Determine if the given dependency has a parameter override.
	 *
	 * @param ReflectionParameter $dependency
	 * @return bool
	 */
	protected function hasParameterOverride($dependency){
		return array_key_exists($dependency->name, $this->getLastParameterOverride());
	}

	/**
	 * Get a parameter override for a dependency.
	 *
	 * @param ReflectionParameter $dependency
	 * @return mixed
	 */
	protected function getParameterOverride($dependency){
		return $this->getLastParameterOverride()[$dependency->name];
	}

	/**
	 * Get the last parameter override.
	 *
	 * @return array
	 */
	protected function getLastParameterOverride(){
		return count($this->with) ? end($this->with) : [];
	}

	/**
	 * 解析非自定义类的原始依赖关系
	 *
	 * @param ReflectionParameter $parameter
	 * @return mixed
	 *
	 * @throws Library\Exception
	 */
	protected function resolvePrimitive(ReflectionParameter $parameter){
		if(!is_null($concrete=$this->getContextualConcrete('$' . $parameter->name))){
			return $concrete instanceof Closure ? $concrete($this) : $concrete;
		}
		
		if($parameter->isDefaultValueAvailable()){
			return $parameter->getDefaultValue();
		}
		
		$this->unresolvablePrimitive($parameter);
	}

	/**
	 * 从容器中解决基于类的依赖关系
	 *
	 * @param ReflectionParameter $parameter
	 * @return mixed
	 *
	 * @throws Library\Exception
	 */
	protected function resolveClass(ReflectionParameter $parameter){
		try{
			return $this->make($parameter->getClass()->name);
		}		
		
		/*
		 * 如果我们不能解析类实例，我们将检查这个值是否是可选的，
		 * 如果它是，我们将返回可选参数值作为依赖项的值，类似默认值
		 * */
		catch(Exception $e){
			if($parameter->isOptional()){
				return $parameter->getDefaultValue();
			}
			
			throw $e;
		}
	}

	/**
	 * 抛出一个异常，具体是不能实例化的
	 *
	 * @param string $concrete
	 * @return void
	 *
	 * @throws Library\Exception
	 */
	protected function notInstantiable($concrete){
		if(!empty($this->buildStack)){
			$previous=implode(', ', $this->buildStack);
			
			$message="Target [$concrete] is not instantiable while building [$previous].";
		}else{
			$message="Target [$concrete] is not instantiable.";
		}
		
		throw new Exception($message);
	}

	/**
	 * 为不可解析的原语抛出异常
	 *
	 * @param ReflectionParameter $parameter
	 * @return void
	 *
	 * @throws Library\Exception
	 */
	protected function unresolvablePrimitive(ReflectionParameter $parameter){
		$message="Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";
		
		throw new Exception($message);
	}

	/**
	 * Register a new resolving callback.
	 *
	 * @param string $abstract
	 * @param Closure|null $callback
	 * @return void
	 */
	public function resolving($abstract,Closure $callback=null){
		if(is_string($abstract)){
			$abstract=$this->getAlias($abstract);
		}
		
		if(is_null($callback) && $abstract instanceof Closure){
			$this->globalResolvingCallbacks[]=$abstract;
		}else{
			$this->resolvingCallbacks[$abstract][]=$callback;
		}
	}

	/**
	 * Register a new after resolving callback for all types.
	 *
	 * @param string $abstract
	 * @param Closure|null $callback
	 * @return void
	 */
	public function afterResolving($abstract,Closure $callback=null){
		if(is_string($abstract)){
			$abstract=$this->getAlias($abstract);
		}
		
		if($abstract instanceof Closure && is_null($callback)){
			$this->globalAfterResolvingCallbacks[]=$abstract;
		}else{
			$this->afterResolvingCallbacks[$abstract][]=$callback;
		}
	}

	/**
	 * Fire all of the resolving callbacks.
	 *
	 * @param string $abstract
	 * @param mixed $object
	 * @return void
	 */
	protected function fireResolvingCallbacks($abstract,$object){
		$this->fireCallbackArray($object, $this->globalResolvingCallbacks);
		
		$this->fireCallbackArray($object, $this->getCallbacksForType($abstract, $object, $this->resolvingCallbacks));
		
		$this->fireAfterResolvingCallbacks($abstract, $object);
	}

	/**
	 * Fire all of the after resolving callbacks.
	 *
	 * @param string $abstract
	 * @param mixed $object
	 * @return void
	 */
	protected function fireAfterResolvingCallbacks($abstract,$object){
		$this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);
		
		$this->fireCallbackArray($object, $this->getCallbacksForType($abstract, $object, $this->afterResolvingCallbacks));
	}

	/**
	 * Get all callbacks for a given type.
	 *
	 * @param string $abstract
	 * @param object $object
	 * @param array $callbacksPerType
	 *
	 * @return array
	 */
	protected function getCallbacksForType($abstract,$object,array $callbacksPerType){
		$results=[];
		
		foreach($callbacksPerType as $type=>$callbacks){
			if($type === $abstract || $object instanceof $type){
				$results=array_merge($results, $callbacks);
			}
		}
		
		return $results;
	}

	/**
	 * Fire an array of callbacks with an object.
	 *
	 * @param mixed $object
	 * @param array $callbacks
	 * @return void
	 */
	protected function fireCallbackArray($object,array $callbacks){
		foreach($callbacks as $callback){
			$callback($object, $this);
		}
	}

	/**
	 * Get the container's bindings.
	 *
	 * @return array
	 */
	public function getBindings(){
		return $this->bindings;
	}

	/**
	 * Get the alias for an abstract if available.
	 *
	 * @param string $abstract
	 * @return string
	 *
	 * @throws LogicException
	 */
	public function getAlias($abstract){
		if(!isset($this->aliases[$abstract])){
			return $abstract;
		}
		
		if($this->aliases[$abstract] === $abstract){
			throw new LogicException("[{$abstract}] is aliased to itself.");
		}
		
		return $this->getAlias($this->aliases[$abstract]);
	}

	/**
	 * Get the extender callbacks for a given type.
	 *
	 * @param string $abstract
	 * @return array
	 */
	protected function getExtenders($abstract){
		$abstract=$this->getAlias($abstract);
		
		if(isset($this->extenders[$abstract])){
			return $this->extenders[$abstract];
		}
		
		return [];
	}

	/**
	 * Remove all of the extender callbacks for a given type.
	 *
	 * @param string $abstract
	 * @return void
	 */
	public function forgetExtenders($abstract){
		unset($this->extenders[$this->getAlias($abstract)]);
	}

	/**
	 * Drop all of the stale instances and aliases.
	 *
	 * @param string $abstract
	 * @return void
	 */
	protected function dropStaleInstances($abstract){
		unset($this->instances[$abstract], $this->aliases[$abstract]);
	}

	/**
	 * Remove a resolved instance from the instance cache.
	 *
	 * @param string $abstract
	 * @return void
	 */
	public function forgetInstance($abstract){
		unset($this->instances[$abstract]);
	}

	/**
	 * Clear all of the instances from the container.
	 *
	 * @return void
	 */
	public function forgetInstances(){
		$this->instances=[];
	}

	/**
	 * Flush the container of all bindings and resolved instances.
	 *
	 * @return void
	 */
	public function flush(){
		$this->aliases=[];
		$this->resolved=[];
		$this->bindings=[];
		$this->instances=[];
		$this->abstractAliases=[];
	}

	/**
	 * Set the globally available instance of the container.
	 *
	 * @return static
	 */
	public static function getInstance(){
		if(is_null(static::$instance)){
			static::$instance=new static();
		}
		
		return static::$instance;
	}

	/**
	 * Set the shared instance of the container.
	 *
	 * @param \Illuminate\Contracts\Container\Container|null $container
	 * @return static
	 */
	public static function setInstance(ContainerContract $container=null){
		return static::$instance=$container;
	}

	/**
	 * Determine if a given offset exists.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function offsetExists($key){
		return $this->bound($key);
	}

	/**
	 * Get the value at a given offset.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function offsetGet($key){
		return $this->make($key);
	}

	/**
	 * Set the value at a given offset.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet($key,$value){
		$this->bind($key, $value instanceof Closure ? $value : function () use ($value){
			return $value;
		});
	}

	/**
	 * Unset the value at a given offset.
	 *
	 * @param string $key
	 * @return void
	 */
	public function offsetUnset($key){
		unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
	}

	/**
	 * Dynamically access container services.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get($key){
		return $this[$key];
	}

	/**
	 * Dynamically set container services.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function __set($key,$value){
		$this[$key]=$value;
	}
}
