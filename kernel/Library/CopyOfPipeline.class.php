<?php
namespace Library;
use \Closure;

class Pipeline{
	protected $container; // 容器实例
	protected $passable; // 通过管道传递的对象
	protected $pipes=[]; // 类管道的数组
	protected $method='handle'; // 每个管道上被调用的方法

	/**
	 * 创建管道实例
	 *
	 * @param \Library\Container|null $container
	 * @return void
	 */
	public function __construct(Container $container=null){
		$this->container=$container;
	}

	/**
	 * 设置通过管道发送的对象
	 *
	 * @param mixed $passable
	 * @return $this
	 */
	public function send($passable){
		$this->passable=$passable;
		return $this;
	}

	/**
	 * 设置管道的数组
	 *
	 * @param array|mixed $pipes
	 * @return $this
	 */
	public function through($pipes){
		$this->pipes=is_array($pipes) ? $pipes : func_get_args();
		return $this;
	}

	/**
	 * 设置调用管道的方法
	 *
	 * @param string $method
	 * @return $this
	 */
	public function via($method){
		$this->method=$method;
		return $this;
	}

	/**
	 * 在管道中运行最后的回调函数
	 *
	 * @param \Closure $destination
	 * @return mixed
	 */
	public function then(Closure $destination){
		$pipeline=array_reduce(
					array_reverse($this->pipes), 
					$this->carry(), 
					$this->prepareDestination($destination)
				);
		return $pipeline($this->passable);
	}

	/**
	 * 解析完整的管道字符串以获取名称和参数
	 *
	 * @param string $pipe
	 * @return array
	 */
	protected function parsePipeString($pipe){
		list($name, $parameters)=array_pad(explode(':', $pipe, 2), 2, []);
		if(is_string($parameters)){
			$parameters=explode(',', $parameters);
		}
		return [$name,$parameters];
	}

	/**
	 * 获取容器实例
	 *
	 * @return \Library\Container
	 * @throws \RuntimeException
	 */
	protected function getContainer(){
		if(!$this->container){
			throw new Exception('A container instance has not been passed to the Pipeline.');
		}
		return $this->container;
	}

	/**
	 * 将回调函数进行封装.
	 *
	 * @param \Closure $destination
	 * @return \Closure
	 */
	protected function prepareDestination(Closure $destination){
		return function ($passable) use ($destination){
			try{
				fastlog('App');
				return $destination($passable);
			}catch(Exception $e){
				return $this->handleException($passable, $e);
			}
		};
	}

	/**
	 * 迭代回调函数
	 *
	 * @return \Closure
	 */
	protected function carry(){
		return function ($stack,$pipe){
			return function ($passable) use ($stack,$pipe){
				try{
					$slice=$this->getSlice();
					$callable=$slice($stack, $pipe);
					return $callable($passable);
				}catch(Exception $e){
					return $this->handleException($passable, $e);
				}
			};
		};
	}
	
	/**
	 * 获取一个表示应用程序切片的闭包
	 *
	 * @return \Closure
	 */
	private function getSlice(){
		return function ($stack,$pipe){
			return function ($passable) use ($stack,$pipe){
				fastlog($pipe);
				if(is_callable($pipe)){
					// 直接调用管道回调函数
					return $pipe($passable, $stack);
				}elseif(!is_object($pipe)){
					// 解析命名的字符串通道，并构建
					list($name, $parameters)=$this->parsePipeString($pipe);
					$pipe=$this->getContainer()->make($name);
					$parameters=array_merge([$passable,$stack], $parameters);
				}else{
					$parameters=[$passable,$stack];
				}
				return method_exists($pipe, $this->method) ? 
						 $pipe->{$this->method}(...$parameters) : $pipe(...$parameters);
			};
		};
	}

	/**
	 * 处理给定的异常
	 *
	 * @param mixed $passable
	 * @param Library\Exception $e
	 * @return mixed
	 *
	 * @throws Library\Exception
	 */
	protected function handleException($passable,Exception $e){
		if(!$passable instanceof Request){
			throw $e;
		}
		$handler=$this->container->make(Exception::class);
		$handler->report($e);
		return $handler->render($passable, $e);
	}
}