<?php
namespace Library;

/**
 * 系统请求类型
 * 
 * @package Library
 */
class Request{
	private $route=null; //路由对象
	private $request=null; //外部Request对象
    
    private $headers=null; //Header信息
    private $servers=null; //Server信息
    
    /**
     * 上下文应用对象
     *
     * @var \Library\Application
     */
    private $context=null;
    
    /**
     * 构造函数（由容器调用）
     *
     * @param Application $context 应用程序对象
     */
	public function __construct(Application $context){
        $this->context=$context;
	}
    
    /**
     * 获取上下文应用对象
     * 
     */
    public function getContext(){
        return $this->context;
    }
	
	/**
	 * 动态方法调用
     * 
	 * @param string $name 方法名称
	 * @param mixed $args 参数
     * 
	 * 目前支持客户端请求方法判断：
	 * isGet()、isPost()、isDelete()、isPut()分别用于判断GET、POST、DELETE、PUT请求方法
	 * 同时支持特定客户端请求判断：
     * isAjax()、isWechat()、isMobile()分别用于Ajax请求、微信端请求、移动端请求判断
	 */
	public function __call($name,$args){
		if(stripos($name, 'is')===0){
			$method=strtoupper(substr($name, 2));
			$suppors=['GET','POST','DELETE','PUT','AJAX','WECHAT','MOBILE'];
			if(in_array($method, $suppors)){
				switch ($method){
					case 'AJAX': //判断ajax请求
						return env('IS_AJAX');
					case 'WECHAT': //判断微信客户端登录
						$user_agent=$this->server('user_agent', 'none');
						return strpos($user_agent,'MicroMessenger')!==false;
					case 'MOBILE':
						return $this->isMobile();
					default: //请求方法判断
						return env('REQUEST_METHOD')==$method;
				}
			}
		}
		return false;
	}
	
	/**
	 * 设置外部请求对象
     * 
	 * @param Request $request 外部响应对象（默认调用传递null）
	 */
	public function setRequest($request=null){
        $this->headers=null;
        $this->request=$request;
		if(!is_null($request)){
            //恢复默认变量为了兼容其它不通过本类获取系统环境变量
            if(isset($request->get)){
			    $_GET=&$request->get;
            }
            if(isset($request->post)){
			    $_POST=&$request->post;
            }
            if(isset($request->files)){
                $_FILES=&$request->files;
            }
            if(isset($request->cookie)){
                $_COOKIE=&$request->cookie;
            }
            $_SERVER=$this->restoreServer($request->server, $request->header);
		}else if(PHP_SAPI=='cli'){
            $_GET=$_POST=$_REQUEST=$_FILES=$_COOKIE=[];
        }
        
        //客户端数据过滤
        $this->filter();
	}
	
	/**
	 * 获取Http请求的头部信息（键名为小写）
     * 
	 * @param string|null|array $key 需要获取的键名，如果为null获取所有，如果为数组则重置请求头部
	 * @param mixed $default 如果key不存在，则返回默认值
	 * @return string|array 
	 */
	public function header($key=null,$default=null){
        if(!is_null($this->request)){
            $headers=&$this->request->header;
            if(!is_null($key)){
                if(is_array($key)){
                    foreach ($key as $k => $v) {
                        $headers[$k]=$v;
                    }
                }else{
                    $value=$this->caseKeyValue($headers, $key);
                    if($value!==null){
                        return $value;
                    }
                    return $default;
                }
            }
            return $headers;
        }else{
            if(!is_null($key)){
                if(is_array($key)){
                    foreach ($key as $k => $v) {
                        $nky='HTTP_'.strtoupper($k);
                        $_SERVER[$nky]=$v;
                    }
                }else{
                    $key=strtoupper(str_replace('-','_',$key));
                    if(isset($_SERVER[$key])){
                        return $_SERVER[$key];
                    }
                    $key='HTTP_'.$key;
                    if(isset($_SERVER[$key])){
                        return $_SERVER[$key];
                    }
                    return $default;
                }
            }else{
                return $this->getHeaders();
            }
        }
	}
	
	/**
	 * 获取Http请求相关的服务器信息（不区分大小写）
     * 
	 * @param string|null|array $name 需要获取的键名，如果为null获取所有，为数组用于设置
	 * @param mixed $default 如果key不存在，则返回默认值
	 * @return string|array 
	 */
	public function &server($name=null,$default=null){
        if(!is_null($this->request)){
            $servers=&$this->request->server;
        }else{
            $servers=&$_SERVER;
        }
        if(!is_null($name)){
            if(is_array($name)){
                foreach ($name as $k => $v) {
                    $nky=strtoupper($k);
                    $servers[$nky]=$v;
                }
            }else{
                $value=$this->caseKeyValue($servers, $name);
                if($value!==null){
                    return $value;
                }
                return $default;
            }
		}
		return $servers;
	}
	
	/**
	 * 获取Http请求的GET参数
     * 
	 * @param string $name 需要获取的键名，如果为null获取所有
	 * @param mixed $default 如果key不存在，则返回默认值
	 * @return string|array 
	 */
	public function &get($name=null,$default=null){
        if(!is_null($this->request)){
            $gets=&$this->request->get;
        }else{
            $gets=&$_GET;
        }
		if(!is_null($name)){
            if(is_array($name)){
                foreach ($name as $k => $v) {
                    $gets[$k]=$v;
                }
            }else{
                if(isset($gets[$name])){
                    return $gets[$name];
                }
                return $default;
            }
		}
		return $gets;
	}
	
	/**
	 * 获取Http请求的POST参数
     * 
	 * @param string|null|array $name 需要获取的键名，如果为null获取所有，为数组用于设置
	 * @param mixed $default 如果key不存在，则返回默认值
	 * @return string|array
	 */
	public function &post($name=null,$default=null){
        if(!is_null($this->request)){
            $posts=&$this->request->post;
        }else{
            $posts=&$_POST;
        }
		if(!is_null($name)){
            if(is_array($name)){
                foreach ($name as $k => $v) {
                    $posts[$k]=$v;
                }
            }else{
                if(isset($posts[$name])){
                    return $posts[$name];
                }
                return $default;
            }
		}
		return $posts;
	}
    
    /**
    * 获取POST/GET输入参数，优先级为: POST> GET
    *
    * @param string $key 获取的键名，默认为null，取所有值
    * @param mixed $default 默认值
    * @return string|array
    */
    public function input($name=null, $default=null){
        if(is_null($name)){
            return array_merge(
                    $this->get(),
                    $this->post()
                );
        }
        
        $type='';
        if(strpos($name,'/')){
            $keys=explode('/', $name, 2);
            $name=trim($keys[0]);
            $type=trim($keys[1]);
        }

        //获取值
        $value=$this->post($name);
        if(is_null($value)){
            $value=$this->get($name, $default);
        }

        //值处理函数
        if($type=='d'){
            return intval($value);
        }else if($type=='f'){
            return floatval($value);
        }else if(!empty($type) && function_exists($type)){
            return $type($value);
        }
        return $value;
    }
	
	/**
	 * 获取Http请求携带的COOKIE信息
     * 
	 * @param string|null|array $name 需要获取的键名，如果为null获取所有，为数组用于设置
	 * @param mixed $default 如果key不存在，则返回默认值
	 * @return string|array
	 */
	public function &cookie($name=null,$default=null){
        if(!is_null($this->request)){
            $cookies=&$this->request->cookie;
        }else{
            $cookies=&$_COOKIE;
        }
		if(!is_null($name)){
            if(is_array($name)){
                foreach ($name as $k => $v) {
                    $cookies[$k]=$v;
                }
            }else{
                if(isset($cookies[$name])){
                    return $cookies[$name];
                }
                return $default;
            }
		}
		return $cookies;
	}
	
	/**
	 * 获取文件上传信息
     * 
	 * @param string $name 需要获取的键名，如果为null获取所有
	 * @return array
	 */
	public function &files($name=null){
        if(!is_null($this->request)){
            $files=&$this->request->files;
        }else{
            $files=&$_FILES;
        }
        if(!is_null($name)){
            return $files[$name];
        }
		return  $files;
	}
	
	/**
	 * 获取原始的POST包体
     * 
	 * @return mixed 返回原始POST数据
	 * @uses 用于非application/x-www-form-urlencoded格式的Http POST请求
	 * */
	public function rawContent(){
        return !is_null($this->request)  ?   
                    $this->request->rawContent() : 
                    (PHP_SAPI=='cli' ? 
                        $GLOBALS['HTTP_RAW_POST_DATA'] : 
                        file_get_contents('php://input')
                    );
	}
	
	/**
	 * 设置路由对象
     * 
	 * @param Route $route 路由对象
	 */
	public function setRoute(Route $route){
		$this->route=$route;
	}
	
	/**
	 * 获取路由对象
     * 
	 * @return Route $route 路由对象
	 */
	public function getRoute(){
		return $this->route;
	}
	
	/**
	 * 获取系统运行模式
     * 
     * @return string 
	 */
	public function getSapiName(){
		return !is_null($this->request) ? 'swoole' : PHP_SAPI;
	}
    
    /**
     * 判断当前是否为移动端登录
     *
     * @return boolean
     */
    public function isMobile(){
        $headers=$this->header();
        $all_http=isset($headers['all-http']) ? $headers['all-http'] : '';
        $mobile_browser=0;
        $agent=isset($headers['user-agent']) ? strtolower($headers['user-agent']):'';
        
        if($agent && preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i',$agent)){
            $mobile_browser++;
        }elseif( (isset($headers['accept'])) && (strpos(strtolower($headers['accept']),'application/vnd.wap.xhtml+xml')!==false)){
            $mobile_browser++;
        }elseif(isset($headers['x-wap-profile'])){
            $mobile_browser++;
        }elseif(isset($headers['profile'])){
            $mobile_browser++;
        }elseif($agent){
            $mobile_ua=substr($agent,0,4);
            $mobile_agents=[
                'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
                'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
                'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
                'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
                'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
                'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
                'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
                'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
                'wapr','webc','winw','winw','xda','xda-'
            ];
            if(in_array($mobile_ua,$mobile_agents)){
                $mobile_browser++;
            }elseif(strpos(strtolower($all_http),'operamini')!==false){
                $mobile_browser++;
            }
        }
        
        if(strpos($agent,'windows')!==false){
            $mobile_browser=0;
        }
        if(strpos($agent,'windows phone')!==false){
            $mobile_browser++;
        }
        return $mobile_browser>0;
    }
    
    
    /**
     * 客户端参数过滤
     */
    private function filter(){
        if(get_magic_quotes_gpc()){
			$_POST=slashes($_POST, 0);
			$_GET=slashes($_GET, 0);
			$_REQUEST=slashes($_REQUEST, 0);
			$_COOKIE=slashes($_COOKIE, 0);
		}
    }
    
	/**
	 * 获取所有header信息
     * 
	 * @return array
	 */
	private function getHeaders(){
		$headers=array();
        $servers=$this->server();
		foreach($servers as $key=>$value){
            if(!strcasecmp('http_', substr($key, 0, 5))){
                $cKey=strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$cKey]=$value;
            }
		}
		if($this->caseKeyValue($servers,'php_auth_digest')!==null){
			$headers['authorization'] = $this->caseKeyValue($servers,'php_auth_digest');
		}else if(
            $this->caseKeyValue($servers,'php_auth_user')!==null &&
            $this->caseKeyValue($servers,'php_auth_pw')!==null
        ){
            $user=$this->caseKeyValue($servers,'php_auth_user');
            $pw=$this->caseKeyValue($servers,'php_auth_pw');
			$headers['authorization'] = base64_encode( $user. ':' . $pw);
		}
		
		if($this->caseKeyValue($servers, 'content_length') !== null){
			$headers['content-length']=$this->caseKeyValue($servers, 'content_length');
		}
		if($this->caseKeyValue($servers, 'content_type') !== null){
			$headers['content-type']=$this->caseKeyValue($servers, 'content_type');
		}
		return $headers;
	}
    
    /**
     * 从Swoole执行环境中恢复$_SERVER全局变量
     *
     * @param array $server Swoole环境中的server变量
     * @param array $header Swoole环境中的header变量
     * @return array
     */
    private function restoreServer($server, $header){
        $servers=array_change_key_case($server, CASE_UPPER);
        foreach ($header as $key => $value) {
            $newKey='HTTP_'.strtoupper(str_replace('-', '_', $key));
            $servers[$newKey]=$value;
        }
        return $servers;
    }
    
    /**
     * 数组键名大小写不敏感查找
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    private function caseKeyValue(&$array, $key){
        if(isset($array[$key])){
            return $array[$key];
        }
        $key=strtolower($key);
        if(isset($array[$key])){
            return $array[$key];
        }
        $key=strtoupper($key);
        if(isset($array[$key])){
            return $array[$key];
        }
        return null;
    }
	
}
