<?php
namespace Library;

class Request{
	static private $middlewares=[]; //中间件数组
	private $params=[]; //绑定的路由参数
	private $disposer=null; //请求处理器
	
	public function __construct(){
		if(get_magic_quotes_gpc()){
			$_POST=slashes($_POST, 0);
			$_GET=slashes($_GET, 0);
			$_REQUEST=slashes($_REQUEST, 0);
			$_COOKIE=slashes($_COOKIE, 0);
		}
		
		//session_id设置，防止客户端不支持cookie设定
		if($sessionid=self::getInput('PHPSESSID')){
			session_id($sessionid);
		}
		
		//路由绑定
		$this->bind();
	}
	
	/*
	 * 获取路由匹配参数
	 * @param string $name 参数名称 如果为null，则返回参数数组
	 * return string|array
	 */
	public function getParam($name=null){
		return is_null($name) ? $this->params : $this->params[$name];
	}
	
	/*
	 * 设置绑定的控制器
	 * @param object $disposer 绑定的控制器
	 */
	public function setDisposer($disposer){
		$this->disposer=$disposer;
	}
	
	/*
	 * 获取绑定的控制器
	 * @return object $disposer 绑定的控制器
	 */
	public function getDisposer(){
		return $this->disposer;
	}

	/*
	 * 检查路由参数是否匹配
	 */
	private function bind(){
		$urls=explode('?',(!IS_CLI ? $_SERVER['REQUEST_URI']:(isset($GLOBALS['argv'][1])?$GLOBALS['argv'][1]:'')),2);
		$url=trim(array_shift($urls),'/');
		//使用路径参数匹配
		if(!empty($url)){
			$handle=$this->matchHandle($url);
			if(!is_null($handle)){
				if(is_string($handle)){
					$results=explode('@', $handle);
					$mcs=explode('/', array_shift($results));
					define('ROUTE_A',$results[0]);
					define('ROUTE_C',ucfirst(array_pop($mcs)));
					!empty($mcs) && define('ROUTE_M',ucfirst(strtolower(array_pop($mcs))));
				}else if(is_callable($handle)){
					$this->setDisposer($handle);
				}
			}
		}
		
		//设置默认路由常量，同时使用传统路由方式匹配模式
		!defined('ROUTE_M') && define('ROUTE_M', defined('BIND_MODULE') ? BIND_MODULE : ucfirst(strtolower($this->getInput('m','Home'))));
		!defined('ROUTE_C') && define('ROUTE_C', defined('BIND_CONTROLLER') ? BIND_CONTROLLER : ucfirst($this->getInput('c', C('default_c'))));
		!defined('ROUTE_A') && define('ROUTE_A', defined('BIND_ACTION') ? BIND_ACTION : $this->getInput('a', C('default_a')));
	}

	/*
	 * 查找路由处理器
	 * @param string $url URL地址
	 * @return string|null
	 */
	private function matchHandle($url){
		$default=C('route.default');
		$routes=empty(SITE_HOST) ? $default : C('route.'.strtolower(SITE_HOST),$default);
		foreach($routes as $key=> $value){
			if(is_array($value)){
				foreach($value as $k=> $v){
					if(!is_null($result=$this->getHandle($url,$k,$v))){
						self::setMiddleware($key);
						return $result;
					}
				}
			}elseif(!is_null($result=$this->getHandle($url,$key,$value))){
				return $result;
			}
		}
		return null;
	}
	
	/*
	 * 获取路由处理器
	 * @param string $url URL参数
	 * @param string $route 路由
	 * @param string|function $handle 处理器 
	 * @return string|null
	 */
	private function getHandle($url,$route,$handle){
		$route=trim($route,'/');
		if(substr_count($route, '/')==substr_count($url, '/')){
			$routes=explode(':', $route, 2);
			$route=trim(array_pop($routes));
			$method=count($routes) ? strtoupper(array_pop($routes)) : 'GET';
			if($method!=REQUEST_METHOD){
				return null;
			}
			if(!strcasecmp($route, $url)){
				//如果url完全匹配（不区分大小写），直接返回
				return $handle;
			}else{
				//否则进行变量类型查找
				$kArrs=explode('/',$route);
				$urlArrs=explode('/',$url);
				
				$isVar=is_string($handle) && strpos($handle, '}')!==false;
				$mCount=count($kArrs);
				foreach($kArrs as $ki=> $kv){
					if(strcasecmp($kv, $urlArrs[$ki])){
						if(strpos($kv, '{')!==false){ //变量匹配检查
							$kvnts=explode('|',trim($kv,'{} '));
							$kvName=$kvnts[0];
							$kvType=isset($kvnts[1]) ? $kvnts[1] : 's';
							if($kvType=='d'){
								if(is_numeric($urlArrs[$ki])){
									$this->params[$kvName]=$urlArrs[$ki];
								}else{
									break;
								}
							}else{
								$this->params[$kvName]=$urlArrs[$ki];
							}
							if($isVar){
								$handle=str_replace('{'.$kvName.'}',$urlArrs[$ki],$handle);
							}
						}else{
							break;
						}
					}
					$mCount--;
				}
				if(!$mCount){
					return $handle;
				}
			}
		}
		return null;
	}
	
	/*
	 * 设置中间件
	 * @param string $name 中间名称
	 * @param array|string $excepts 排除的方法名称
	 */
	public static function setMiddleware($name,$excepts=[]){
		$middlewares=C('middleware.*',[]);
		if(isset($middlewares[$name])){
			if(!isset(self::$middlewares[$name])){
				self::$middlewares[$name]=(array)$excepts;
			}else{
				self::$middlewares[$name]=array_unique(array_merge(self::$middlewares[$name],(array)$excepts));
			}
		}
	}
	
	/*
	 * 获取中间件（或根据方法名称返回可用中间件）
	 * @param string $name 方法名称
	 * @return array 中间数组
	 * 说明：如果提供方法名称，则根据方法名称返回可用中间件
	 */
	public static function getMiddleware($name=null){
		$classes=[];
		$middlewares=C('middleware.*',[]);
		foreach(self::$middlewares as $key => $values){
			if(!is_null($name)){
				if(!in_array($name, $values)){
					$classes[]=$middlewares[$key];
				}
			}else{
				$classes[]=$middlewares[$key];
			}
		}
		return $classes;
	}
	
	/*
	 *  获取输入参数，非空值，依次为GET、POST
	 *  @param string $name 参数名称
	 *  @param mixed $default 默认值
	 *  @handle string|\Closure 处理函数
	 *  @return mixed
	 * */
	public static function getInput($name,$default='',$handle=null){
		$value=isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $default);
		return !is_null($handle) && ((is_string($handle) && function_exists($handle)) || ($handle instanceof \Closure)) ?
					$handle($value) : $value;
	}
	
	/*
	 * 检查参数是否存在
	 * @param string $name 参数名称
	 * @return mixed
	 */
	public static function checkInput($name){
		return isset($_GET[$name]) || isset($_POST[$name]);
	}
}
