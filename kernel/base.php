<?php
/**** 【定义系统基础常量】 ****/
function_exists('date_default_timezone_set') && date_default_timezone_set('Etc/GMT-8'); //设置时区
define('STEEZE_VERSION','1.2.2'); //系统版本
define('INI_STEEZE', true); //初始化标识
define('SYS_START_TIME', microtime()); // 设置系统开始时间

/**** 【定义服务器端路径】 ****/
define('DS', DIRECTORY_SEPARATOR); //简化目录分割符
define('KERNEL_PATH', dirname(__FILE__) . DS); //框架目录
!defined('APP_PATH') && define('APP_PATH', KERNEL_PATH . '..' . DS . 'app' . DS); //应用目录
!defined('VENDOR_PATH') && define('VENDOR_PATH', KERNEL_PATH . '..' . DS . 'vendor' . DS); //外部库目录
!defined('STORAGE_PATH') && define('STORAGE_PATH', KERNEL_PATH . '..' . DS . 'storage' . DS); //数据存储目录
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
!defined('USE_DEFUALT_HANDLE') && define('USE_DEFUALT_HANDLE', env('use_defualt_handle',false));
//默认主机，命令行模式时使用
define('DEFAULT_HOST',env('default_host','127.0.0.1'));
//默认应用名称
!defined('DEFAULT_APP_NAME') && define('DEFAULT_APP_NAME','home');

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
				if(strpos($path, 'App'.DS)===0){
					$pos=strpos($path, '/',4);
					include APP_PATH.strtolower(substr($path,4,$pos-4)).substr($path,$pos).'.php';
				}else if(strpos($path, 'Vendor'.DS)===0){
					include VENDOR_PATH.substr($path,7).'.php';
				}else{
					include KERNEL_PATH.$path.'.class.php';
				}
			}catch (\Library\Exception $e){
				E(L('class for {0} is not exists',$path),$e->getCode());
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
		$c=str_replace('/','\\',ucwords(strtolower(trim($c,'\\/.')),'/'));
		$concrete=str_replace('\\\\','\\','App\\'.ucfirst(strtolower($m)).'\\Controller\\'.$c);
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
		$baseDir=(empty($module) ? KERNEL_PATH : APP_PATH . (is_string($module) ? strtolower($module) : env('ROUTE_M','')) . DS);
		$path=str_replace(DS.DS,DS,$baseDir .'Helper' . DS . $name . '.php');
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
	 * @return object
	 */
	public static function config($name,$key='',$default=''){
		static $appConfigs=[]; //应用缓存
		static $globalConfigs=[]; //全局缓存
		
		//应用名称
		$appName=env('ROUTE_M','/');
		
		// 如果为第二个参数为数组则直接写入配置
		if(is_array($key)){
			$appConfigs[$appName][$name]=(isset($appConfigs[$appName][$name]) ? array_merge($appConfigs[$appName][$name], $key) : $key);
			return $appConfigs[$appName][$name];
		}
		
		if(
			!isset($globalConfigs[$name]) && 
			is_file($globalPath=STORAGE_PATH . 'Conf' . DS . $name . '.php')
		){
			$globalConfigs[$name]=include($globalPath);
		}
		
		if(
			!isset($appConfigs[$appName][$name]) &&
			is_file($appPath=simplify_ds(APP_PATH . $appName . DS . 'Conf' . DS . $name . '.php'))
		){
			$moduleConfig=include($appPath);
			$appConfigs[$appName][$name]= is_array($moduleConfig) ? $moduleConfig : null;
		}
		
		return $key!=='' ? (
					isset($appConfigs[$appName][$name][$key]) ?
						$appConfigs[$appName][$name][$key] :
						(isset($globalConfigs[$name][$key]) ? $globalConfigs[$name][$key] : $default)
				) : (
					isset($appConfigs[$appName][$name]) ?
						$appConfigs[$appName][$name] :
						(isset($globalConfigs[$name]) ? $globalConfigs[$name] : $default)
			);
		
	}
	
}
