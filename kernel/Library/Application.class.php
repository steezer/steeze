<?php
namespace Library;
use Service\Storage\Manager as Storage;
use Loader;
use Library\View;
use Library\Controller;

class Application{

	public function __construct(){
		//路由绑定
		$params=(new Param())->bind();
		
		// 设置应用配置
		$this->setConfig();
		
		// 运行应用
		$this->run($params);
	}

	/**
	 * 应用运行
	 * 
	 * @param $params 从路由中获取的参数
	 */
	private function run($params){
		/*
		 * 调用控制器方法，控制器方法不能以“_”开头，
		 * 说明：以“_”开头的方法用于模版内部控制器方法调用
		 * */
		if(
			strpos(ROUTE_A, '_') !== 0 && 
				($controller=Loader::controller(ROUTE_C,$params)) && 
			is_callable(array($controller, ROUTE_A))
		){
			$result=Controller::run($controller, ROUTE_A,$params);
			View::render($result);
		}
	}

	/**
	 * 初始化系统模块配置
	 */
	private function setConfig(){
		// 设置错误处理函数
		if(C('errorlog')){
			set_error_handler('my_error_handler');
		}else{
			if(APP_DEBUG){
				ini_set('display_errors', 'on');
				error_reporting(version_compare(PHP_VERSION, '5.4', '>=') ? E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT : E_ALL ^ E_NOTICE ^ E_WARNING);
			}else{
				error_reporting(E_ERROR | E_PARSE);
			}
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
		!defined('IS_AJAX') && define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || Param::getParam(C('VAR_AJAX_SUBMIT', 'ajax'))) ? true : false);
		
		//配置存储服务
		Storage::connect(STORAGE_TYPE);
	}
	
}
