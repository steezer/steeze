<?php
namespace Library;

class Param{
	public function __construct(){
		if(get_magic_quotes_gpc()){
			$_POST=slashes($_POST, 0);
			$_GET=slashes($_GET, 0);
			$_REQUEST=slashes($_REQUEST, 0);
			$_COOKIE=slashes($_COOKIE, 0);
		}
		
		//session_id设置，防止客户端不支持cookie设定
		if($sessionid=self::getParam('PHPSESSID')){
			session_id($sessionid);
		}
	}
	
	// 检查路由参数是否匹配
	public function bind(){
		$params=[];
		$result=$this->getRoute($params);
		if(!is_null($result)){
			$results=explode('@', $result);
			$mcs=explode('/', array_shift($results));
			define('ROUTE_A',$results[0]);
			define('ROUTE_C',ucfirst(array_pop($mcs)));
			!empty($mcs) && define('ROUTE_M',ucfirst(strtolower(array_pop($mcs))));
		}
		// 定义模块变量
		!defined('ROUTE_M') && define('ROUTE_M', defined('BIND_MODULE') ? BIND_MODULE : ucfirst(strtolower($this->getParam('m','Home'))));
		!defined('ROUTE_C') && define('ROUTE_C', defined('BIND_CONTROLLER') ? BIND_CONTROLLER : ucfirst($this->getParam('c', C('default_c'))));
		!defined('ROUTE_A') && define('ROUTE_A', defined('BIND_ACTION') ? BIND_ACTION : $this->getParam('a', C('default_a')));
		return $params;
	}
	
	//查找匹配的路由
	private function getRoute(&$params=[]){
		$urls=explode('?', $_SERVER['REQUEST_URI'],2);
		$url=trim(array_shift($urls),'/');
		$configs=C('route.'.strtolower(SITE_HOST),C('route.default'));
		foreach($configs as $k=> $v){
			$k=trim($k,'/');
			if(substr_count($k, '/')==substr_count($url, '/')){
				if(!strcasecmp($k, $url)){
					//如果url完全匹配（不区分大小写），直接返回
					return $v;
				}else{
					//否则进行变量类型查找
					$kArrs=explode('/',$k);
					$urlArrs=explode('/',$url);
					$isVar=strpos($v, '}')!==false;
					$mCount=count($kArrs);
					foreach($kArrs as $ki=> $kv){
						if(strcasecmp($kv, $urlArrs[$ki])){
							if(strpos($kv, '{')!==false){ //变量匹配检查
								$kvnts=explode('|',trim($kv,'{} '));
								$kvName=$kvnts[0];
								$kvType=isset($kvnts[1]) ? $kvnts[1] : 's';
								if($kvType=='d'){
									if(is_numeric($urlArrs[$ki])){
										$params[$kvName]=$urlArrs[$ki];
									}else{
										break;
									}
								}else{
									$params[$kvName]=$urlArrs[$ki];
								}
								if($isVar){
									$v=str_replace('{'.$kvName.'}',$urlArrs[$ki],$v);
								}
							}else{
								break;
							}
						}
						$mCount--;
					}
					if(!$mCount){
						return $v;
					}
				}
			}
		}
		return null;
	}
	
	// 获取参数，非空值
	public static function getParam($para,$default='',$func=null){
		$return=isset($_GET[$para]) && !empty($_GET[$para]) ? $_GET[$para] : (isset($_POST[$para]) && !empty($_POST[$para]) ? $_POST[$para] : $default);
		return !is_null($func)&&function_exists($func) ? $func($return) : $return;
	}
	
	// 检查参数是否存在
	public static function checkParam($para){
		return isset($_GET[$para]) || isset($_POST[$para]);
	}
}
