<?php

namespace Library;

use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionFunction;

class Container{
	
	protected static $instance; //当前全局可用的容器
	protected $instances=[]; //容器中的共享实例
	protected $aliases=[]; //容器的别名
	protected $extenders=[]; //扩展闭包服务
	protected $with=[]; //参数栈

	/**
	 * 从容器中解析给定的类型
	 *
	 * @param string $concrete 需要构造的类名
	 * @param array $parameters 参数
	 * @param bool $isCache 是否需要存储，默认为true
	 * @return mixed
	 */
	public function make($concrete,$parameters=[],$isCache=true){
		return $this->resolve($concrete, $parameters,$isCache);
	}
	
	/**
	 * 实例化给定类型的具体实例
	 *
	 * @param string $concrete
	 * @return mixed
	 *
	 * @throws \Library\Exception
	 */
	public function build($concrete){
		try{
			if($concrete instanceof Closure){
				return $concrete($this, $this->getLastParameterOverride());
			}
			$reflector=new ReflectionClass($concrete);
			$constructor=$reflector->getConstructor();
			//没有构造函数的构建
			if(is_null($constructor)){
				return new $concrete();
			}
			
			//构造函数带有参数的构建
			$dependencies=$constructor->getParameters();
			$instances=$this->resolveDependencies($dependencies);
			return $reflector->newInstanceArgs($instances);
		}catch(\Exception $e){
			E($e);
		}
	}
	
	/**
	 * 对象方法调用
	 * 
	 * @param object|string $concrete 实例或类名
	 * @param string $method 方法名称
	 * @param array $parameters 方法参数
	 * @return mixed
	 */
	
	public function callMethod($concrete,$method,array $parameters=[]){
		if(!is_object($concrete)){
			$concrete=$this->resolve($concrete, $parameters);
		}
		try{
			if(method_exists($concrete, $method)){
				$reflector=new ReflectionClass(get_class($concrete));
				$this->with[]=$parameters;
				$dependencies=$reflector->getMethod($method)->getParameters();
				$instances=$this->resolveDependencies($dependencies);
				array_pop($this->with);
				return call_user_func_array(array($concrete,$method), $instances);
			}
		}catch(\Exception $e){
			E($e);
		}
	}
	
	
	/**
	 * Closure匿名函数调用
	 *
	 * @param Closure $closure 匿名函数对象
	 * @param array $parameters 方法参数
	 * @return mixed
	 */
	
	public function callClosure($closure,array $parameters=[]){
		try{
			if($closure instanceof Closure){
				$reflector=new ReflectionFunction($closure);
				$this->with[]=$parameters;
				$dependencies=$reflector->getParameters();
				$instances=$this->resolveDependencies($dependencies);
				array_pop($this->with);
				return $reflector->invokeArgs($instances);
			}
		}catch(\Exception $e){
			E($e);
		}
	}
	
	/**
	 * 从容器中解析给定的类型.
	 *
	 * @param string $concrete
	 * @param array $parameters
	 * @param bool $isCache
	 * @return mixed
	 */
	protected function resolve($concrete,$parameters=[],$isCache=true){
		$concrete=$this->getAlias($concrete);
		/**
		 * 如果类型的一个实例是目前管理作为一个单例,我们就返回一个现有的实例,
		 * 而不是实例化新的实例, 所以开发人员可以保持每次都使用相同的对象实例。
		 */
		if($isCache && isset($this->instances[$concrete])){
			return $this->instances[$concrete];
		}
		
		$this->with[]=$parameters;
		
		//构建实例化对象
		$object=$this->build($concrete);
		
		/**
		 * 如果定义任何扩展的类型,则获取扩展,并将其应用到对象。
		 * 通过这种扩展，我们可以更改配置或对对象进行修饰。
		 */
		foreach($this->getExtenders($concrete) as $extender){
			$object=$extender($object, $this);
		}
		
		/**
		 * 如果需要，则保存对象到容器中 
		 */
		if($isCache){
			$this->instances[$concrete]=$object;
		}
		
		array_pop($this->with);
		return $object;
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
			$depClass=$dependency->getClass();
			$name=$dependency->name;
			if($this->hasParameterOverride($dependency)){
				$depDefault=$this->getParameterOverride($dependency);
				if(!is_null($depClass) && is_numeric($depDefault)){
					$depObject=$this->resolveClass($dependency);
					//如果实现了模型类（或者是模型类的子类）
					if($depObject instanceof Model){
						//@!模型不缓存
						$this->forgetInstance($depClass->name);
						$pk=$depObject->getPk();
						if(is_subclass_of($depObject,Model::class)){
							//自定义模型需要表名和路由变量名称相同
							$depObject->getTableName(false)==$name && 
								$depObject->where($pk.'='.$depDefault)->find();
						}else{
							
							//则根据路由变量名称为表名，查找ID主键为路由参数值的记录，
							$depObject->table($name)->where($pk.'='.$depDefault)->find();
						}
					}
					$results[]=$depObject;
				}else{
					$results[]=$depDefault;
				}
				continue;
			}
			
			if(!is_null($depClass)){
				$depObject=$this->resolveClass($dependency);
				if($depObject instanceof Model){
					//@!模型不缓存
					$this->forgetInstance($depClass->name);
				}
			}else{
				$depObject=$this->resolvePrimitive($dependency);
			}
			$results[]=$depObject;
		}

		return $results;
	}

	/**
	 * 确定给定的依赖是否有一个参数.
	 *
	 * @param ReflectionParameter $dependency
	 * @return bool
	 */
	protected function hasParameterOverride($dependency){
		return array_key_exists($dependency->name, $this->getLastParameterOverride());
	}

	/**
	 * 获取一个依赖项的参数
	 *
	 * @param ReflectionParameter $dependency
	 * @return mixed
	 */
	protected function getParameterOverride($dependency){
		return $this->getLastParameterOverride()[$dependency->name];
	}

	/**
	 * 获取最后的参数.
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
	 * @throws \Library\Exception
	 */
	protected function resolvePrimitive(ReflectionParameter $parameter){
		return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
	}

	/**
	 * 从容器中解决基于类的依赖关系
	 *
	 * @param ReflectionParameter $parameter
	 * @return mixed
	 *
	 * @throws \Library\Exception
	 */
	protected function resolveClass(ReflectionParameter $parameter){
		try{
			return $this->make($parameter->getClass()->name);
		}		
		
		/*
		 * 如果我们不能解析类实例，我们将检查这个值是否是可选的，
		 * 如果它是，我们将返回可选参数值作为依赖项的值，类似默认值
		 * */
		catch(\Exception $e){
			if($parameter->isOptional()){
				return $parameter->getDefaultValue();
			}
			E($e);
		}
	}
	
	/**
	 * 根据指定的类型获取特定扩展
	 *
	 * @param string $concrete
	 * @return array
	 */
	protected function getExtenders($concrete){
		$concrete=$this->getAlias($concrete);
		
		if(isset($this->extenders[$concrete])){
			return $this->extenders[$concrete];
		}
		
		return [];
	}

	/**
	 * 获取类型别名
	 *
	 * @param string $concrete
	 * @return string
	 */
	public function getAlias($concrete){
		if(!isset($this->aliases[$concrete])){
			return $concrete;
		}
		return $this->getAlias($this->aliases[$concrete]);
	}

	/**
	 * 删除特定类型扩展
	 *
	 * @param string $concrete
	 * @return void
	 */
	public function forgetExtenders($concrete){
		unset($this->extenders[$this->getAlias($concrete)]);
	}

	/**
	 * 更加类型删除特定实例缓存
	 *
	 * @param string $concrete
	 * @return void
	 */
	public function forgetInstance($concrete){
		if(!is_null($concrete)){
			$classname=is_object($concrete) ? get_class($concrete) : $concrete;
			unset($this->instances[$classname]);
		}
	}

	/**
	 * 清空容器中的所有实例
	 *
	 * @return void
	 */
	public function forgetInstances(){
		$this->instances=[];
	}

	/**
	 * 获取容器单例
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
	 * 设置容器实例
	 *
	 * @param \Library\Container|null $container
	 * @return static
	 */
	public static function setInstance(Container $container=null){
		return static::$instance=$container;
	}
}
