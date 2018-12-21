<?php
namespace Library;

/**
 * 中间件基类
 * 
 * @package Library
 */
class Middleware{
    /**
     * 应用上下文对象
     *
     * @var \Library\Application
     */
    protected $context=null;
    
    /**
     * 构造应用上下文对象
     *
     * @param \Library\Application $context
     */
    public function __construct(Application $context){
        $this->context=$context;
    }
    
    /**
     * 中间件处理器
     *
     * @param \Closure $next 下个处理器
     * @param \Library\Request $request 应用Request对象
     * @param \Library\Response $response 应用Response对象
     * @return \Closure
     */
	public function handle(\Closure $next, $request, $response){
        return $next($request,$response);
	}
    
}
