<?php

namespace Library;

use Closure;
use Exception;
use Loader as load;

/**
 * 上下文应用程序类
 * 
 * @package Library
 */
class Application extends Context
{

    public function __construct($request = null, $response = null)
    {
        //初始化上下文请求和响应对象
        parent::__construct($request, $response);
        //设置异常处理器
        $this->setErrorHandle();
    }

    public function __destruct()
    {
        //取消系统异常处理程序
        $this->setErrorHandle(false);
    }

    /**
     * 运行应用并返回结果到浏览器
     * 
     * @param array $config 启动配置参数，如果为空则从系统环境中获取
     * @throws Exception
     *  
     * 示例：[
     *    'url' => 'https://www.steeze.cn/api/test?id=1', //带Get参数的URL地址
     *    'method'=> 'GET', //请求方法
     *    'data' => [ 'name' => 'spring' ], //Post参数，此参数如果设置，自动转为POST
     *    'header' => [ 'TOKEN' => '123456' ], //Header信息
     *    'cookie' => [ 'na' => 'test' ], //Cookie信息
     *  ]
     */
    public function start($config = array())
    {
        //恢复输出
        $this->response->setIsEnd(false);

        //初始化系统
        $this->init($config);

        //获取路由及当前控制器方法名
        $route = $this->request->getRoute();
        $isClosure = is_callable($route->getDisposer());
        $route_a = env('ROUTE_A', false);

        //输出数据到浏览器
        $this->response->flush(
            (new Pipeline($this))
                ->send($this->request, $this->response)
                ->through($route->getMiddleware(!$isClosure && $route_a ? $route_a : null))
                ->then(array($this, 'run'))
        );
    }

    /**
     * 使用自定义的Request对象运行系统
     *
     * @param Request $request
     * @param Response $response
     * @return string
     * @throws Exception
     */
    public function run(Request $request, Response $response = null)
    {
        //获取路由参数
        $route=$request->getRoute();
        $params = $route->getParam();
        $disposer = $route->getDisposer();
        $route_m = env('ROUTE_M', '');
        $route_c = env('ROUTE_C', false);
        $route_a = env('ROUTE_A', false);
        
        //生成控制器的路由处理器
        if($route_c && is_null($disposer)){
            $disposer=load::controller($route_c, $route->getParam(), $this);
            $route->setDisposer($disposer);
        }

        if ($disposer instanceof Closure) {
            //直接运行回调函数
            return $this->invokeFunc($disposer, $params);
        } else if (
            //控制器方法不能以“_”开头，以“_”开头的方法用于模板内部控制器方法调用
            $route_a && strpos($route_a, '_') !== 0 && is_object($disposer) &&
            is_callable(array($disposer, $route_a))
        ) {
            //运行控制器方法
            return $this->invokeMethod($disposer, $route_a, $params);
        } else if (
            C('use_view_route', env('use_view_route', true)) &&
            $route_c && $route_a &&
            !(is_null($viewer = $this->fetch($route_c . '/' . $route_a . '@:' . $route_m, $params)))
        ) {
            //直接返回渲染后的模版视图
            return $viewer;
        } else {
            //返回错误页面
            throw new Exception(L('Page not found'), -404);
        }
    }
}
