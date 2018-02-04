<?php
namespace Library;
use Loader;

class Application{
	private $request=null;
	private $response=null;
	
	public function __construct($request=null, $response=null){
		//初始化请求和响应对象
		$this->request=make(Request::class);
		$this->response=make(Response::class);
		$this->request->setRequest($request);
		$this->response->setResponse($response);
		
		//加载应用环境变量
		$this->appEnv();
		
		//路由构建和路由绑定
		$route=new Route($this->request);
		$this->request->setRoute($route);

		//系统配置
		$this->appConfig();
	}

	/**
	 * 运行应用并返回结果到浏览器
	 */
	public function start(){
		//设置路由处理器
		$route=$this->request->getRoute();
		$isClosure=is_callable($route->getDisposer());
		
		//获取绑定的控制器参数
		$route_c=env('ROUTE_C',false);
		$route_a=env('ROUTE_A',false);
		
		//设置路由控制器
		if(!$isClosure && $route_c){
			$route->setDisposer(Loader::controller($route_c,$route->getParam()));
		}
		
		//启动应用，并渲染返回结果
		View::render((new Pipeline(Container::getInstance()))
				->send($this->request,$this->response)
				->through($route->getMiddleware(!$isClosure && $route_a ? $route_a : null))
				->then($this->dispatchToRouter())
			);
		
		//释放视图对象
		Container::getInstance()->forgetInstance(View::class);
		
		//输出到前端
		$this->response->end();
	}
	
	/*
	 * 获取路由处理函数
	 * return \Closure
	 */
	private function dispatchToRouter(){
		return function (Request $request,Response $response) {
			//获取路由参数
			$params=$request->getRoute()->getParam();
			$disposer=$request->getRoute()->getDisposer();
			$route_m=env('ROUTE_M','');
			$route_c=env('ROUTE_C',false);
			$route_a=env('ROUTE_A',false);
			if($disposer instanceof \Closure){
				//直接运行回调函数
				return Container::getInstance()->callClosure($disposer,$params);
			}else if(
				//控制器方法不能以“_”开头，以“_”开头的方法用于模板内部控制器方法调用
				$route_a && strpos($route_a, '_') !== 0 && is_object($disposer) &&
					is_callable(array($disposer, $route_a))
			){
				//运行控制器方法
				return Controller::run($disposer, $route_a,$params);
			}elseif(
				C('use_view_route',env('use_view_route',true)) &&
				$route_c && $route_a &&
				!(is_null($viewer=view($route_c.'/'.$route_a.'@:'.$route_m,$params)))
			){
				//直接访问模板
				return $viewer;
			}else{
				return E(L('Page not found'),404,true);
			}
		};
	}

	
	/**
	 * 加载应用环境变量
	 */
	private function appEnv(){
		//设置错误处理函数
		if(APP_DEBUG){
			function_exists('ini_set') && ini_set('display_errors', 'on');
			error_reporting(version_compare(PHP_VERSION, '5.4', '>=') ? E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT : E_ALL ^ E_NOTICE ^ E_WARNING);
		}else{
			error_reporting(E_ERROR | E_PARSE);
		}
		
		$server=$this->request->server();
		//设置应用环境
		Loader::env('PHP_SAPI',$this->request->getSapiName());
		//请求时间
		Loader::env('NOW_TIME',$server['request_time']);
		//检查是否微信登录
		Loader::env('WECHAT_ACCESS',strpos($this->request->header('user-agent',''),'MicroMessenger')!==false);
		
		//当前请求方法判断
		$method=strtoupper(isset($server['request_method']) ? $server['request_method'] : 'GET');
		Loader::env('REQUEST_METHOD', $method);
		Loader::env('IS_GET', $method == 'GET' ? true : false);
		Loader::env('IS_POST', $method == 'POST' ? true : false);
		
		//系统唯一入口定义，兼任windows系统和cli模式
		$entry='/'.(isset($server['script_name']) ? trim(str_replace(DS,'/',str_replace(ROOT_PATH,'/',str_replace('/',DS,$server['script_name']))),'/') : 'index.php');
		$protocol=(isset($server['server_port']) && $server['server_port'] == '443' ? 'https://' : 'http://');
		$port=(isset($server['server_port']) && $server['server_port'] != '80' ? ':' . $server['server_port'] : '');
		Loader::env('SYSTEM_ENTRY', $entry);
		Loader::env('SITE_PROTOCOL', $protocol);
		Loader::env('SITE_PORT', $port);
		
		//设置访问域名
		$host=strtolower($this->request->header('host',(isset($server['server_name']) ? $server['server_name'] : DEFAULT_HOST)));
		Loader::env('SITE_HOST',strpos($host, ':')!==false ? substr($host,0,strpos($host, ':')) : $host);
		Loader::env('SITE_URL', $protocol . env('SITE_HOST') . ($protocol=='https://'?'' : $port)); // 网站首页地址
		Loader::env('ROOT_URL', rtrim(dirname($entry),'/').'/'); //系统根目录路径
		!env('ASSETS_URL') && Loader::env('ASSETS_URL', env('ROOT_URL') . 'assets/'); //静态文件路径
		!env('UPLOAD_URL') && Loader::env('UPLOAD_URL', env('ASSETS_URL') . 'ufs/'); //上传图片访问路径
		!env('SYS_VENDOR_URL') && Loader::env('SYS_VENDOR_URL', env('ASSETS_URL') . 'assets/vendor/'); //外部资源扩展路径
	}
	
	/**
	 * 初始化系统模块配置（此处配置可作用于模块中）
	 */
	private function appConfig(){
		// 设置本地时差
		function_exists('date_default_timezone_set') && date_default_timezone_set(C('timezone'));

		// 定义是否为ajax请求
		Loader::env('IS_AJAX', ((strtolower($this->request->header('x_requested_with','')) == 'xmlhttprequest') || I(C('VAR_AJAX_SUBMIT', 'ajax'))) ? true : false);
	}
	
}
