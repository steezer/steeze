<?php
/**** 【定义系统基础常量】 ****/
function_exists('date_default_timezone_set') && date_default_timezone_set('Etc/GMT-8'); //设置时区
define('STEEZE_VERSION','1.0.1'); //系统版本
define('INI_STEEZE', true); //初始化标识
define('SYS_START_TIME', microtime()); // 设置系统开始时间

/**** 【定义服务器端路径】 ****/
define('DS', DIRECTORY_SEPARATOR); //简化目录分割符
define('KERNEL_PATH', dirname(__FILE__) . DS); //框架目录
define('APP_PATH', KERNEL_PATH . '..' . DS . 'app' . DS); //应用目录
define('STORAGE_PATH', KERNEL_PATH . '..' . DS . 'storage' . DS);
define('CACHE_PATH', STORAGE_PATH . 'Cache' . DS); //缓存目录
define('LOGS_PATH', STORAGE_PATH . 'Logs' . DS); //日志目录
!defined('ROOT_PATH') && define('ROOT_PATH', dirname(KERNEL_PATH) . DS . 'public' . DS); //网站根目录路径
define('ASSETS_PATH', ROOT_PATH . 'assets' . DS); //资源文件路径
define('UPLOAD_PATH', ASSETS_PATH . 'ufs' . DS); //文件上传目录路径
!defined('STORAGE_TYPE') && define('STORAGE_TYPE', (function_exists('saeAutoLoader') ? 'Sae' : 'File'));


/**** 【运行环境判断】 ****/
//加载系统函数库和环境变量
Loader::helper('system');
//加载系统环境变量
Loader::env();

/**** 【从环境变量初始化常量】 ****/
// 系统默认在开发模式下运行
!defined('APP_DEBUG') && define('APP_DEBUG', (bool)env('app_debug',true)); 
//当找不到处理器时，是否使用默认处理器
define('USE_DEFUALT_HANDLE', env('use_defualt_handle',false)); 
//默认主机，命令行模式时使用
define('DEFAULT_HOST',env('default_host','127.0.0.1'));

//注册类加载器
spl_autoload_register('Loader::import');
//配置错误处理
set_exception_handler(array('\Library\Exception', 'render'));


class Loader{
	
	/**
	 * 初始化应用程序
	 */
	public static function app($request=null, $response=null){
		$app=new Library\Application($request, $response);
		return $app->start();
	}
	
	/**
	 * 加载环境变量
	 * @param string $key 环境变量键名，如果为null则重写设置日志
	 * @param string $value 环境变量键值
	 * @param string $default 默认值
	 */
	public static function env($key=null,$value=null,$default=null){
		if(is_null($key)){
			$path=KERNEL_PATH.'..'.DS.'.env';
			if(is_file($path) && is_array($result=parse_ini_file($path))){
				$_ENV=array_merge($_ENV,array_change_key_case($result,CASE_UPPER));
			}
			return $_ENV;
		}else if(!is_null($value)){
			$_ENV[strtoupper($key)]=$value;
			return $value;
		}
		$key=strtoupper($key);
		return isset($_ENV[$key]) ? $_ENV[$key] : $default;
	}
	
	/**
	 * 类加载器 
	 */
	public static function import($path){
		$path=str_replace('\\', DS, $path);
		if(strpos($path, DS)){
			try{
				include (strpos($path, 'App'.DS)===0 ? 
						APP_PATH.substr($path,4).'.php' : 
						KERNEL_PATH.$path.'.class.php');
			}catch (\Library\Exception $e){
				E($e->getMessage(),$e->getCode());
			}
		}
	}

	/**
	 * 加载控制器 可以指定模块，以“.”分割：“模块名.控制器”
	 *
	 * @param string $name 类名称
	 * @param number $initialize 是否初始化
	 * @param string $para 传递给类初始化的参赛
	 * @return object
	 */
	public static function controller($name,array $parameters=[]){
		if($pos=strpos($name, '.', 1)){
			$m=substr($name, 0, $pos);
			$c=substr($name, $pos + 1);
		}else{
			$m=env('ROUTE_M');
			$c=$name;
		}
		$concrete='App\\'.ucfirst(strtolower($m)).'\\Controller\\'.ucfirst(strtolower($c));
		$container=Library\Container::getInstance();
		try{
			return $container->make($concrete,$parameters);
		}catch (\Exception $e){
			return null;
		}
	}

	/**
	 * 加载函数库
	 *
	 * @param string $func 函数库名
	 * @param string|mixed $moudule 当是字符串为模型名称，如果是true，则为当前模型
	 * @return boolean
	 */
	public static function helper($name,$module=null){
		static $helpers=[];
		$baseDir=(empty($module) ? KERNEL_PATH : APP_PATH . (is_string($module) ? ucfirst($module) : env('ROUTE_M')) . DS);
		$path=$baseDir .'Helper' . DS . $name . '.php';
		$key=md5($path);
		if(isset($helpers[$key])){
			return true;
		}
		if(is_file($path)){
			try{
				include_once $path;
			}catch(Exception $e){
				return false;
			}
		}else{
			$helpers[$key]=false;
			return false;
		}
		$helpers[$key]=true;
		return true;
	}

	/**
	 * 加载配置文件
	 *
	 * @param string $name 配置文件
	 * @param string $key 要获取的配置键值
	 * @param string $default 默认配置，当获取配置项目失败时该值发生作用
	 * @param string $reload 强制重新加载
	 * @return object
	 */
	public static function config($name,$key='',$default='',$reload=false){
		static $configs=[];
		
		// 如果为第二个参数为数组则直接写入配置
		if(is_array($key)){
			$configs[$name]=(isset($configs[$name]) ? array_merge($configs[$name], $key) : $key);
			return $configs[$name];
		}
		
		if(!$reload && isset($configs[$name])){
			return empty($key) ? $configs[$name] : 
					(isset($configs[$name][$key]) ? $configs[$name][$key] : $default);
		}
		
		$globalPath=STORAGE_PATH . 'Conf' . DS . $name . '.php';
		if(is_file($globalPath)){
			$configs[$name]=include_once($globalPath);
		}
		
		if(env('ROUTE_M',false)){
			$modulePath=APP_PATH . env('ROUTE_M') . DS . 'Conf' . DS . $name . '.php';
			if(is_file($modulePath)){
				$moduleConfig=include_once($modulePath);
				if(is_array($moduleConfig)){
					if(isset($configs[$name])){
						$configs[$name]=array_merge($configs[$name],$moduleConfig);
					}else{
						$configs[$name]=$moduleConfig;
					}
				}
			}
		}
		
		return empty($key) ? (!empty($configs[$name]) ? $configs[$name] : $default) : 
				(isset($configs[$name][$key]) ? $configs[$name][$key] : $default);
	}
	
}
