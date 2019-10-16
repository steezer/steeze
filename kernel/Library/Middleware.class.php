<?php
namespace Library;
use Closure;

/**
 * 中间件基类
 * 
 * @package Library
 */
class Middleware{
    /**
     * 应用上下文对象
     *
     * @var Application
     */
    protected $context=null;
    
    /**
     * 构造应用上下文对象
     *
     * @param Application $context
     */
    public function __construct(Application $context){
        $this->context=$context;
    }
    
    /**
     * 中间件处理器
     *
     * @param Closure $next 下个处理器
     * @param Request $request 应用Request对象
     * @param Response $response 应用Response对象
     * @return Closure
     */
	public function handle($next, $request, $response){
        return call_user_func_array($next, array(&$request, &$response));
	}
    
}
