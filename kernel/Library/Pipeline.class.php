<?php
namespace Library;
use Closure;
use Exception;

/**
 * 管道控制类型
 * 
 * @package Library
 */
class Pipeline{
    protected $container; // 容器实例
    protected $passables=array(); // 通过管道传递的对象
    protected $pipes=array(); // 类管道的数组
    protected $method='handle'; // 每个管道上被调用的方法
    private $callStacks=array(); // 调用栈

    /**
     * 创建管道实例
     *
     * @param Container|null $container
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
     * 解析完整的管道字符串以获取名称和参数
     *
     * @param string $pipe
     * @return array
     */
    protected function parsePipeString($pipe){
        list($name, $parameters)=array_pad(explode(':', $pipe, 2), 2, array());
        if(is_string($parameters)){
            $parameters=explode(',', $parameters);
        }
        return array($name, $parameters);
    }

    /**
     * 获取容器实例
     *
     * @return Container
     * @throws Exception
     */
    protected function getContainer(){
        if(!$this->container){
            throw new Exception('A container instance has not been passed to the Pipeline.');
        }
        return $this->container;
    }

    /**
     * 在管道中运行最后的回调函数
     *
     * @param Closure $destination
     * @return mixed
     */
    public function then($destination){
        //将第一个调用入栈
        $pipeline=$this->reduce(
                    array_reverse($this->pipes),
                    array($this, 'carry'),
                    $destination
                );
        return call_user_func_array($pipeline, $this->passables);
    }

    /**
     * 迭代回调函数
     *
     * @return Closure
     */
    protected function carry($stack, $pipe){
        //中间件入栈
        $this->callStacks[]=array($stack, $pipe);
        return array($this, 'pipe');
    }
    
    /**
     * 可被调用的中间件
     */
    public function pipe(){
        //中间件出栈
        $stacks=array_pop($this->callStacks);
        if($stacks==null){
            throw new Exception('No pipe for call.');
        }
        $stack=$stacks[0];
        $pipe=$stacks[1];
        $passables=func_get_args();
        return $this->runPipe($stack, $pipe, $passables);
    }
    
    /**
     * 调用中间件
     *
     * @param Closure $stack
     * @param mixed $pipe
     * @param array $passables
     */
    private function runPipe($stack, $pipe, $passables){
        if(is_callable($pipe)){
            // 直接调用管道回调函数
            return call_user_func_array($pipe,array_merge(array($stack),$passables));
        }elseif(!is_object($pipe)){
            // 解析命名的字符串通道，并构建
            list($name, $parameters)=$this->parsePipeString($pipe);
            $pipe=$this->getContainer()->make($name);
            $parameters=array_merge(array($stack), $passables, $parameters);
        }else{
            $parameters=array_merge(array($stack), $passables);
        }
        return call_user_func_array(
                (method_exists($pipe, $this->method) ? array($pipe, $this->method) : $pipe),
                $parameters
            );
    }
    
    /**
     * 用回调函数迭代地将数组简化为单一的值（同array_reduce）
     *
     * @param array $input
     * @param callable $callback
     * @param mixed $init
     */
    private function reduce($input, $callback, $init){
        if(empty($input)){
            return $init;
        }
        array_unshift($input, $init);
        $total=count($input);
        $result=$input[0];
        for ($i=0; $i < $total-1; $i++) {
            $result=call_user_func($callback, $result, $input[$i+1]);
        }
        return $result;
    }

}
