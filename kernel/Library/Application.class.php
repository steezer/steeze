<?php
namespace Library;
use Service\Storage\Manager as Storage;
use Loader;

class Application{

	public function __construct(){
		//路由绑定
		$request=new Request();
		
		// 设置应用配置
		$this->setConfig();
		
		// 运行应用
		$this->run($request);
	}

	/**
	 * 应用运行
	 * 
	 * @param $params 从路由中获取的参数
	 */
	private function run(Request $request){
		//设置路由处理器
		!($isClosure=is_callable($request->getDisposer())) && 
			$request->setDisposer(Loader::controller(ROUTE_C,$request->getParam()));
		
		//执行中间件并输出处理结果
		View::render((new Pipeline(Container::getInstance()))
				->send($request)
				->through($request->getMiddleware(!$isClosure ? ROUTE_A : null))
				->then($this->dispatchToRouter())
			);
	}
	
	/*
	 * 获取路由处理函数
	 * return \Closure
	 */
	private function dispatchToRouter(){
		return function (Request $request) {
			//获取路由参数
			$params=$request->getParam();
			$disposer=$request->getDisposer();
			if($disposer instanceof \Closure){
				//直接运行回调函数
				return $disposer(...array_values($params));
			}else if(
				//控制器方法不能以“_”开头，以“_”开头的方法用于模版内部控制器方法调用
				strpos(ROUTE_A, '_') !== 0 && is_object($disposer) &&
				is_callable(array($disposer, ROUTE_A))
			){
				//运行控制器方法
				return Controller::run($disposer, ROUTE_A,$params);
			}else{
				//直接访问模版
				return view(ROUTE_M.'@'.ROUTE_C.'/'.ROUTE_A,$params);
			}
		};
	}

	/**
	 * 初始化系统模块配置
	 */
	private function setConfig(){
		// 设置错误处理函数
		if(APP_DEBUG){
			ini_set('display_errors', 'on');
			error_reporting(version_compare(PHP_VERSION, '5.4', '>=') ? E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT : E_ALL ^ E_NOTICE ^ E_WARNING);
		}else{
			error_reporting(E_ERROR | E_PARSE);
		}
		// 设置本地时差
		function_exists('date_default_timezone_set') && date_default_timezone_set(C('timezone'));
		
		// 配置应用常量
		if(!defined('STYLE_URL')){
			$style=rtrim(C('default_resx'),'/');
			define('STYLE_URL', strpos($style,'://')!==false ? $style.'/' :
						STATIC_URL . (strpos($style,'/')===0 ? ltrim($style,'/') .'/' : 'app/'. strtolower(ROUTE_M) . '/' . ($style === '' ? '' : $style . '/')) 
				);
		}
		
		// 定义是否为ajax请求
		!defined('IS_AJAX') && define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || Request::getInput(C('VAR_AJAX_SUBMIT', 'ajax'))) ? true : false);
		
		//配置存储服务
		Storage::connect(STORAGE_TYPE);
	}
	
}
