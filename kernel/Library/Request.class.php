<?php
namespace Library;

class Request{
	private $params=[]; //绑定的路由参数
	
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
	 * 检查路由参数是否匹配
	 */
	private function bind(){
		$urls=explode('?',(!IS_CLI ? $_SERVER['REQUEST_URI']:(isset($GLOBALS['argv'][1])?$GLOBALS['argv'][1]:'')),2);
		$url=trim(array_shift($urls),'/');
		//使用路径参数匹配
		if(!empty($url)){
			$result=$this->getHandle($url);
			if(!is_null($result)){
				$results=explode('@', $result);
				$mcs=explode('/', array_shift($results));
				define('ROUTE_A',$results[0]);
				define('ROUTE_C',ucfirst(array_pop($mcs)));
				!empty($mcs) && define('ROUTE_M',ucfirst(strtolower(array_pop($mcs))));
			}
		}
		
		//使用传统方式匹配
		!defined('ROUTE_M') && define('ROUTE_M', defined('BIND_MODULE') ? BIND_MODULE : ucfirst(strtolower($this->getInput('m','Home'))));
		!defined('ROUTE_C') && define('ROUTE_C', defined('BIND_CONTROLLER') ? BIND_CONTROLLER : ucfirst($this->getInput('c', C('default_c'))));
		!defined('ROUTE_A') && define('ROUTE_A', defined('BIND_ACTION') ? BIND_ACTION : $this->getInput('a', C('default_a')));
	}
	
	/*
	 * 查找路由处理器
	 * @param string $url URL地址
	 * @return string|null
	 */
	private function getHandle($url){
		$default=C('route.default');
		$routes=empty(SITE_HOST) ? $default : C('route.'.strtolower(SITE_HOST),$default);
		foreach($routes as $route=> $handle){
			$route=trim($route,'/');
			if(substr_count($route, '/')==substr_count($url, '/')){
				if(!strcasecmp($route, $url)){
					//如果url完全匹配（不区分大小写），直接返回
					return $handle;
				}else{
					//否则进行变量类型查找
					$kArrs=explode('/',$route);
					$urlArrs=explode('/',$url);
					$isVar=strpos($handle, '}')!==false;
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
		}
		return null;
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
