<?php
namespace Library;
use \Closure;

/**
 * 管道控制类型
 * 
 * @package Library
 */
class Pipeline{
	protected $container; // 容器实例
	protected $passables=[]; // 通过管道传递的对象
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
	 * @param mixed $passables
	 * @return $this
	 */
	public function send(){
		$this->passables=func_get_args();
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
		return call_user_func_array($pipeline, $this->passables);
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
		return function () use ($destination){
			$passables=func_get_args();
			return call_user_func_array($destination, $passables);
		};
	}

	/**
	 * 迭代回调函数
	 *
	 * @return \Closure
	 */
	protected function carry(){
		return function ($stack,$pipe){
			return function () use ($stack,$pipe){
				$passables=func_get_args();
				$slice=$this->getSlice();
                $callable=$slice($stack, $pipe);
                return call_user_func_array($callable, $passables);
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
			return function () use ($stack,$pipe){
				$passables=func_get_args();
				if(is_callable($pipe)){
					// 直接调用管道回调函数
					return call_user_func_array($pipe,array_merge([$stack],$passables));
				}elseif(!is_object($pipe)){
					// 解析命名的字符串通道，并构建
					list($name, $parameters)=$this->parsePipeString($pipe);
					$pipe=$this->getContainer()->make($name);
					$parameters=array_merge([$stack], $passables, $parameters);
				}else{
					$parameters=array_merge([$stack], $passables);
				}
				return call_user_func_array(
						(method_exists($pipe, $this->method) ? array($pipe,$this->method) : $pipe),
						$parameters
					);
			};
		};
	}

}