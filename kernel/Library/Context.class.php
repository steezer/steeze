<?php
namespace Library;
use Loader as load;

/**
 * 系统上下文类
 * 
 * @package Library
 */
class Context extends Container{
    
    private $config=array(); //系统配置
    protected $isInit=false; //是否已经初始化
    
    /**
     * 内置Controller对象
     *
     * @var Controller
     */
    private $controller=null;
	
    /**
     * Request对象
     *
     * @var Request
     */
    protected $request=null; 
    
    /**
     * Response对象
     *
     * @var Response
     */
	protected $response=null;
    
	public function __construct($request=null, $response=null){
		//初始化请求和响应对象
		$this->request=$this->make('\Library\Request');
		$this->response=$this->make('\Library\Response');
		$this->request->setRequest($request);
		$this->response->setResponse($response);
        
        //非swoole模式下运行将应用对象绑定到容器静态实例
        if($this->request->getSapiName()!='swoole'){
            self::$instance=$this;
        }
	}
    
    /**
     * 默认调用控制器方法（一般在Closure路由中调用）
     *
     * @param string $name 方法名称
     * @param array $args 参数数组
     * @return mixed
     */
    public function __call($name, $args=array()){
        if(is_null($this->controller)){
            $this->controller=$this->make('\Library\Controller');
            $this->controller->setContext($this);
        }
        return call_user_func_array(array(&$this->controller, $name), $args);
    }
    
    /**
     * 获取Request对象
     *
     * @return Request
     */
    public function getRequest(){
        return $this->request;
    }
    
    /**
     * 获取Response对象
     *
     * @returnResponse
     */
    public function getResponse(){
        return $this->response;
    }
    
    /**
     * 获取配置
     *
     * @param string $key 配置键名
     * @return array
     */
    public function getConfig($key=null){
        return is_null($key) ? $this->config : $this->config[$key];
    }
    
    /**
     * 设置或取消系统异常处理程序
     * 
     * @param bool $isSet 是否设置应用异常处理程序
     */
    protected function setErrorHandle($isSet=true){
        if($isSet){
            //设置错误处理函数
            error_reporting(APP_DEBUG_LEVEL);
            $handle=$this->make('\Library\AppException');
            $handle->setContext($this);
            set_error_handler(array($handle, 'onAppError'), APP_DEBUG_LEVEL);
            set_exception_handler(array($handle, 'onAppException'));
        }else{
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * 系统初始化
     *
     * @param array $config 启动配置参数
     */
    protected function init($config=array()){
        if(!$this->isInit || $this->config!=$config){
            //保存配置
            $this->config=$config;
            
            //初始化系统环境
            $this->initSysEnv($config);
            
            //加载应用环境
            $this->loadAppEnv();
            
            //路由构建和绑定
            $route=new Route($this->request);
            $route->bind(
                $this->request->server('request_path'), 
                $this->request->server('server_host')
            );
            $this->request->setRoute($route);

            //系统配置
            $this->appConfig();
            
            //初始化处理器
            $isClosure=is_callable($route->getDisposer());
            $route_c=env('ROUTE_C',false);
            if(!$isClosure && $route_c){
                $controller=load::controller($route_c, false);
                $middleWares=call_user_func(array($controller, 'middleware'));
                $this->setMiddleware($middleWares);
            }
            
            //设置初始化状态
            $this->isInit=true;
        }
    }
    
    /**
     * 初始化系统环境
     *
     * @param array $config 默认配置
     * @return void
     */
    private function initSysEnv($config){
        //设置配置Header
        if(
            isset($config['header']) && 
            is_array($config['header']) && 
            !empty($config['header'])
        ){
            $header=array_change_key_case($config['header'], CASE_LOWER);
        }else{
            $header=array();
        }
        
        //设置配置POST信息
        $get=&$this->request->get();
        $post=&$this->request->post();
        $cookie=&$this->request->cookie();
        $server=&$this->request->server();
        if($get===null){
            $get=array();
        }
        if($post===null){
            $post=array();
        }
        if($cookie===null){
            $cookie=array();
        }
        
        //配置的变量初始化到全局变量
        if(isset($config['data'])){
            if(is_array($config['data'])){
                $post=array_merge($post, $config['data']);
            }else{
                $GLOBALS['HTTP_RAW_POST_DATA']=strval($config['data']);
            }
        }
        
        //系统运行模式及入口
        $server['php_sapi']=$this->request->getSapiName();
        $server['system_entry']='/'.(
                                isset($server['script_name']) ? 
                                    trim(str_replace(
                                        array('/', ROOT_PATH, DS), 
                                        array(DS, '/', '/'), 
                                        $server['script_name']
                                    ), '/')
                                    : 'index.php'
                            );
        $host = isset($server['server_name']) ? $server['server_name'] : '';
        $path = '/';
        
        //命令行运行模式下的配置
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
                    $get=array_merge($get, $gets);
                }
            }
            
            //从第2个命令行参数设置POST参数
            if(isset($argvs[2])){
                $server['request_method']='POST';
                if(
                    strpos($argvs[2],'=')===false && 
                    strpos($argvs[2],'&')===false
                ){
                    $GLOBALS['HTTP_RAW_POST_DATA']=$argvs[2];
                }else{
                    parse_str($argvs[2], $posts);
                    if(count($posts)==1 && current($posts)===''){
                        unset($posts);
                        $GLOBALS['HTTP_RAW_POST_DATA']=$argvs[2];
                    }else{
                        $post=array_merge($post, $posts);
                    }
                }
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
                $cookie=array_merge($cookie, $cookies);
            }
            
        }else{
            $paths=explode('?',$this->request->server('request_uri'),2);
            $path=array_shift($paths);
            $httpHost=isset($header['host']) ? $header['host'] : $this->request->header('host');
            if(!empty($httpHost)){
                $host=strpos($httpHost, ':')===false ? $httpHost :
                        substr($httpHost, 0, strpos($httpHost, ':')) ;
            }
        }
        
        //设置SESSION
        $sessionKey=C('var_session_id','PHPSESSID');
        $sessionId=isset($cookie[$sessionKey]) ? $cookie[$sessionKey] : (
                     isset($header[$sessionKey]) ? $header[$sessionKey] : (
                        isset($post[$sessionKey]) ? $post[$sessionKey] : (
                            isset($get[$sessionKey]) ? $get[$sessionKey] : null
                    )));
        if(isset($sessionId)){
            $header['session_id']=$sessionId;
        }
        if(!empty($header)){
            $this->request->header($header);
        }
        //重新设置$_REQUEST全局变量
        $_REQUEST=array_merge($get, $post, $cookie);
        
        //客户端请求主机名称（域名）
        $server['server_host']=$host!='' ? $host : DEFAULT_HOST;
        
        //处理包含入口的原始访问路径
        if(($entryPos=stripos($path, $server['system_entry']))!==false){
            if($entryPos===0){
                //例如：将"/index.php/user/list"格式地址处理为"/user/list"
                $path=substr($path, strlen($server['system_entry']));
            }else if(
                $entryPos==($pathLen=strlen($path)-strlen($server['system_entry']))
            ){
                //例如：将"/test/index.php"格式地址处理为"/test"
                $path=substr($path, 0, $pathLen);
            }
        }
		
        //请求路径（必须以"/"开头，以非"/"结尾）
		$server['request_path']='/'.trim($path,'/');
    }
	
	/**
	 * 加载应用环境变量
	 */
	private function loadAppEnv(){
        //首次初始化环境时执行
        if(!$this->isInit){
            // 设置本地时差
		    function_exists('date_default_timezone_set') && date_default_timezone_set(C('timezone'));
        }

		//设置应用环境
		load::env('PHP_SAPI', $this->request->server('php_sapi'));
		//请求时间
		load::env('NOW_TIME', $this->request->server('request_time', time()));
		//检查是否微信登录
        $userAgent=$this->request->header('user-agent');
		load::env('WECHAT_ACCESS',$userAgent!==null && strpos($userAgent,'MicroMessenger')!==false);
		//设置session
        load::env('SESSION_ID', $this->request->header('session_id'));
        
		//当前请求方法判断
		$method=strtoupper($this->request->server('request_method','GET'));
		load::env('REQUEST_METHOD', $method);
		load::env('IS_GET', $method == 'GET' ? true : false);
		load::env('IS_POST', $method == 'POST' ? true : false);
		
		//系统唯一入口定义，兼任windows系统和cli模式
		$host=$this->request->server('server_host');
        $port=$this->request->server('server_port', 80);
        $entry=$this->request->server('system_entry');
        $protocol=$this->request->server('request_scheme', ($port == 443 ? 'https' : 'http')).'://';
        
		load::env('SYSTEM_ENTRY', $entry);
		load::env('SITE_PROTOCOL', $protocol);
		load::env('SITE_PORT', $port);
		
		//设置访问域名
        load::env('SITE_HOST',$host);
		load::env('SITE_URL', $protocol . $host . ($port==80 || $port==443 ? '' : ':'.$port)); // 网站首页地址
		load::env('ROOT_URL', rtrim(str_replace('\\','/',dirname($entry)),'/').'/'); //系统根目录路径
		
        !env('ASSETS_URL') && load::env('ASSETS_URL', env('ROOT_URL') . 'assets/'); //静态文件路径
		!env('UPLOAD_URL') && load::env('UPLOAD_URL', env('ASSETS_URL') . 'ufs/'); //上传图片访问路径
		!env('SYS_VENDOR_URL') && load::env('SYS_VENDOR_URL', env('ASSETS_URL') . 'assets/vendor/'); //外部资源扩展路径
	}
	
	/**
	 * 初始化系统模块配置（此处配置可作用于模块中）
	 */
	private function appConfig(){
		// 定义是否为ajax请求
        $xRequestedWith=$this->request->header('x-requested-with');
		load::env('IS_AJAX', (
                (
                    isset($xRequestedWith) && 
                    strtolower($xRequestedWith) == 'xmlhttprequest'
                ) || 
                $this->request->server('php_sapi')=='cli' ||
                $this->request->input(C('VAR_AJAX_SUBMIT', 'ajax'), false)
        ) ? true : false);
	}
    
    /**
     * 设置路由中间件
     *
     * @param array $middleWares
     */
    private function setMiddleware($middleWares){
        if(!empty($middleWares)){
            if(is_array($middleWares)){
                // 支持数组方式设置
                if(is_array($middleWares[0])){
                    // 例如：array( array('init', 'code'), array('auth', 'login,test') )
                    foreach ($middleWares as $middleWare) {
                        $this->setMiddleware($middleWare);
                    }
                }else{
                    // 例如：array('init>auth', 'login,code')
                    $names=explode('>', $middleWares[0]);
                    $methods=isset($middleWares[1]) ? 
                                (is_string($middleWares[1]) ? explode(',', $middleWares[1]) : $middleWares[1]) : 
                                array();
                    foreach($names as $name){
                        Route::setMiddleware($name, $methods);
                    }
                }
            }else{
                // 例如：init>auth:test,test
                $names=explode('>', $middleWares);
                foreach($names as $name){
                    if(strpos($name, ':')){
                        $cname=explode(':', $name);
                        $methods=explode(',', $cname[1]);
                        Route::setMiddleware($cname[0], $methods);
                    }else{
                        Route::setMiddleware($name);
                    }
                }
            }
        }
    }
    
}
