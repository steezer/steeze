<?php
namespace Library;
use Loader;

class Application{
	private $request=null; //Request对象
	private $response=null; //Response对象
    private $config=[]; //系统配置
    private $isInit=false; //是否已经初始化
	
	public function __construct($request=null, $response=null){
		//初始化请求和响应对象
		$this->request=make('\Library\Request');
		$this->response=make('\Library\Response');
		$this->request->setRequest($request);
		$this->response->setResponse($response);
	}

	/**
	 * 运行应用并返回结果到浏览器
     * 
     * @param array $config 启动配置参数，如果为空则从系统环境中获取
     * @example 
     * 示例：[
     *    'url' => 'https://www.steeze.cn/api/test?id=1', //带Get参数的URL地址
     *    'method'=> 'GET', //请求方法
     *    'data' => [ 'name' => 'spring' ], //Post参数，此参数如果设置，自动转为POST
     *    'header' => [ 'TOKEN' => '123456' ], //Header信息
     *    'cookie' => [ 'na' => 'test' ], //Cookie信息
     *  ]
	 */
	public function start($config=array()){
        //恢复输出
        $this->response->setIsEnd(false);
        
        //初始化系统
        (!$this->isInit || $config!=$this->config) &&
            $this->init($config);
        
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
		Container::getInstance()->forgetInstance('\Library\View');
		//释放路由控制器对象
		Container::getInstance()->forgetInstance($route->getDisposer());
		
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
			}else if(
				C('use_view_route',env('use_view_route',true)) && 
					$route_c && $route_a &&
				   !(is_null($viewer=view($route_c.'/'.$route_a.'@:'.$route_m,$params)))
			){
				//直接返回渲染后的模版视图
				return $viewer;
			}else{
				//返回错误页面
				return E(L('Page not found'),404,true);
			}
		};
	}

    /**
     * 系统初始化
     *
     * @param array $config 启动配置参数
     */
    private function init($config){
        //保存配置
        $this->config=$config;
        
        $server=&$this->request->server();
        $header=&$this->request->header();
        
        //初始化系统环境
        $this->initSysEnv($server, $header, $config);
        
        //加载应用环境
		$this->loadAppEnv($server, $header);
		
		//路由构建和绑定
		$route=new Route($this->request);
        $route->bind($server['request_path'], $server['server_host']);
		$this->request->setRoute($route);

		//系统配置
		$this->appConfig($server, $header);
        
        //设置初始化状态
        $this->isInit=true;
    }
    
    /**
     * 初始化系统环境
     *
     * @param array &$server 默认服务器变量
     * @param array &$header 默认发送头
     * @param array $config 默认配置
     * @return void
     */
    private function initSysEnv(&$server, &$header, $config){
        if(
            isset($config['header']) && 
            is_array($config['header']) && 
            !empty($config['header'])
        ){
            $header=array_merge($header, array_change_key_case($config['header'], CASE_LOWER));
        }
        
        $server['php_sapi']=$this->request->getSapiName();
        $server['system_entry']='/'.(
                                isset($server['script_name']) ? 
                                    trim(str_replace(
                                        array('/',ROOT_PATH,DS), 
                                        array(DS,'/','/'), 
                                        $server['script_name']
                                    ),'/')
                                    : 'index.php'
                            );
        $host=isset($header['host']) ? $header['host'] : DEFAULT_HOST;
        $path = '/';
        if($this->request->getSapiName() == 'cli'){
            //解析命令行路由参数，
            //例如：$ php index.php https://api.steeze.cn/test/api?id=23 name=23&year=23
            
            $argvs=&$GLOBALS['argv'];
            
            $server['request_method']=isset($config['data']) ? 'POST' : 
                            (isset($config['method']) ? $config['method'] : 'GET');
            $server['server_port']=80;
            $server['script_name']=$argvs[0];
            
            //从第1个命令行参数设置环境变量
            if(
                isset($argvs[1]) || 
                (isset($config['url']) && !empty($config['url']))
            ){
                $argv=parse_url(isset($argvs[1]) ? $argvs[1] : $config['url']);
                if(isset($argv['host'])){
                    $host=$argv['host'];
                }
                if(isset($argv['scheme'])){
                    $server['request_scheme']=$argv['scheme'];
                }
                if(isset($argv['port'])){
                    $server['server_port']=$argv['port'];
                }
                if(isset($argv['path'])){
                    $path=$argv['path'];
                }
                //设置GET参数
                if(isset($argv['query'])){
                    parse_str($argv['query'], $gets);
                    $_GET=array_merge($_GET, $gets);
                }
            }
            
            //从第2个命令行参数设置POST参数
            if(isset($argvs[2])){
                parse_str($argvs[2], $posts);
                $_POST=array_merge($_POST, $posts);
                $server['request_method']='POST';
            }
            
            //从第3个命令行参数获取Header信息
            if(isset($argvs[3])){
                parse_str($argvs[3], $headers);
                if(!empty($headers)){
                    $header=array_merge($header, array_change_key_case($headers, CASE_LOWER));
                }
            }
            
            //从第4个命令行参数获取Cookie信息
            if(isset($argvs[4])){
                parse_str($argvs[4], $cookies);
                $_COOKIE=array_merge($_COOKIE, $cookies);
            }
            
        }else{
            $paths=explode('?',$this->request->server('REQUEST_URI'),2);
            $path=array_shift($paths);
            if(isset($server['server_name']) && !empty($server['server_name'])){
                $host=$server['server_name'];
            }
            if(strpos($host, ':')!==false){
                $host=substr($host,0,strpos($host, ':'));
            }
        }
        
        //客户端请求主机名称（域名）
        $server['server_host']=$host;
        
        //例如：将"/index.php/user/list"格式地址处理为"/user/list"
		if(stripos($path, $server['system_entry'])===0){
			$path=substr($path, strlen($server['system_entry']));
		}
        //请求路径（必须以"/"开头，以非"/"结尾）
		$server['request_path']='/'.trim($path,'/');
    }
	
	/**
	 * 加载应用环境变量
     * 
     * @param array &$server 服务器变量
     * @param array &$header 发送头
	 */
	private function loadAppEnv(&$server, &$header){
        //首次初始化环境时执行
        if(!$this->isInit){
            //设置错误处理函数
            if(APP_DEBUG){
                function_exists('ini_set') && ini_set('display_errors', 'on');
                error_reporting(version_compare(PHP_VERSION, '5.4', '>=') ? E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT : E_ALL ^ E_NOTICE ^ E_WARNING);
            }else{
                error_reporting(E_ERROR | E_PARSE);
            }
            // 设置本地时差
		    function_exists('date_default_timezone_set') && date_default_timezone_set(C('timezone'));
        }

		//设置应用环境
		Loader::env('PHP_SAPI', $server['php_sapi']);
		//请求时间
		Loader::env('NOW_TIME', isset($server['request_time']) ? $server['request_time'] : time());
		//检查是否微信登录
		Loader::env('WECHAT_ACCESS',isset($header['user-agent']) && strpos($header['user-agent'],'MicroMessenger')!==false);
		
		//当前请求方法判断
		$method=strtoupper(isset($server['request_method']) ? $server['request_method'] : 'GET');
		Loader::env('REQUEST_METHOD', $method);
		Loader::env('IS_GET', $method == 'GET' ? true : false);
		Loader::env('IS_POST', $method == 'POST' ? true : false);
		
		//系统唯一入口定义，兼任windows系统和cli模式
		$host=$server['server_host'];
        $port=isset($server['server_port']) ? intval($server['server_port']) : 80;
        $entry=$server['system_entry'];
        $protocol=(isset($server['request_scheme']) && !empty($server['request_scheme']) ? $server['request_scheme']: ($port == 443 ? 'https' : 'http')).'://';
        
		Loader::env('SYSTEM_ENTRY', $entry);
		Loader::env('SITE_PROTOCOL', $protocol);
		Loader::env('SITE_PORT', $port);
		
		//设置访问域名
        Loader::env('SITE_HOST',$host);
		Loader::env('SITE_URL', $protocol . $host . ($port==80 || $port==443 ? '' : ':'.$port)); // 网站首页地址
		Loader::env('ROOT_URL', rtrim(str_replace('\\','/',dirname($entry)),'/').'/'); //系统根目录路径
		
        !env('ASSETS_URL') && Loader::env('ASSETS_URL', env('ROOT_URL') . 'assets/'); //静态文件路径
		!env('UPLOAD_URL') && Loader::env('UPLOAD_URL', env('ASSETS_URL') . 'ufs/'); //上传图片访问路径
		!env('SYS_VENDOR_URL') && Loader::env('SYS_VENDOR_URL', env('ASSETS_URL') . 'assets/vendor/'); //外部资源扩展路径
	}
	
	/**
	 * 初始化系统模块配置（此处配置可作用于模块中）
     * 
     * @param array &$server 服务器变量
     * @param array &$header 发送头
	 */
	private function appConfig(&$server, &$header){
		// 定义是否为ajax请求
		Loader::env('IS_AJAX', (
                (
                    isset($header['x-requested-with']) && 
                    strtolower($header['x-requested-with']) == 'xmlhttprequest'
                ) || 
                $server['php_sapi']=='cli' ||
                I(C('VAR_AJAX_SUBMIT', 'ajax'))
        ) ? true : false);
	}
	
}
