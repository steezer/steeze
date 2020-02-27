<?php
/**
 * 系统运行基础文件
 * 
 * 此文件主要定义系统基础常量和加载器，如果需要写命令行程序，只需要引入此文件即可
 * 
 * @package default
 */

//版本检测，低于php5.4不被支持
version_compare(PHP_VERSION, '5.4', '<') &&
    exit('PHP versions smaller than 5.4 are not supported');

 //设置时区
function_exists('date_default_timezone_set') && date_default_timezone_set('Etc/GMT-8');

/**
 * 系统当前版本号
 */
define('STEEZE_VERSION', '1.3.2');

/**
 * 系统初始化标志
 */
define('INI_STEEZE', true);

/**
 * 使用命名空间
 */
define('USE_NAMESPACE', true);

/**
 * 系统开始时间值
 */
define('SYS_START_TIME', microtime());

/**
 * 系统目录分隔符
 */
define('DS', DIRECTORY_SEPARATOR);

/**
 * 内核框架路径
 */
define('KERNEL_PATH', dirname(__FILE__) . DS);

/**
 * 应用目录（支持自定义）
 */
!defined('APP_PATH') && define('APP_PATH', KERNEL_PATH . '..' . DS . 'app' . DS);

/**
 * 应用目录（支持自定义）
 */
!defined('VENDOR_PATH') && define('VENDOR_PATH', KERNEL_PATH . '..' . DS . 'vendor' . DS);

/**
 * 数据存储目录（支持自定义，权限为可读写）
 */
!defined('STORAGE_PATH') && define('STORAGE_PATH', KERNEL_PATH . '..' . DS . 'storage' . DS);

/**
 * 缓存文件目录（权限为可读写）
 */
define('CACHE_PATH', STORAGE_PATH . 'Cache' . DS);

/**
 * 日志目录（权限为可读写）
 */
define('LOGS_PATH', STORAGE_PATH . 'Logs' . DS);

/**
 * 数据目录（权限为可读写）
 */
define('DATA_PATH', STORAGE_PATH . 'Data' . DS);

/**
 * 入口文件根目录路径（支持自定义）
 */
!defined('ROOT_PATH') && define('ROOT_PATH', dirname(KERNEL_PATH) . DS . 'public' . DS);

/**
 * 资源文件路径
 */
define('ASSETS_PATH', ROOT_PATH . 'assets' . DS);

/**
 * 上传文件路径
 */
!defined('UPLOAD_PATH') && define('UPLOAD_PATH', ROOT_PATH . 'ufs' . DS);

/**
 * 文件存储类型
 */
!defined('STORAGE_TYPE') && define('STORAGE_TYPE', (function_exists('saeAutoLoader') ? 'Sae' : 'File'));

// 加载系统函数库
Loader::helper('system');
// 加载环境变量
Loader::env();

/**
 * 系统默认在调试模式下运行（支持自定义常量或环境变量app_debug），建议在生产系统中配置环境变量为false
 */
!defined('APP_DEBUG') && define('APP_DEBUG', (bool)env('app_debug', true)); 

/**
 * 系统错误信息显示级别（支持自定义）
 */
!defined('APP_DEBUG_LEVEL') && define(
    'APP_DEBUG_LEVEL',
    APP_DEBUG ? (E_ALL ^ E_STRICT ^ E_NOTICE) : (E_ERROR | E_PARSE)
);

/**
 * 当找不到处理器时，是否使用默认处理器（支持自定义常量或环境变量use_defualt_handle）
 */
!defined('USE_DEFUALT_HANDLE') && define('USE_DEFUALT_HANDLE', env('use_defualt_handle', false));

/**
 * 默认主机，命令行模式时使用（支持定义环境变量default_host）
 */
define('DEFAULT_HOST', env('default_host', '127.0.0.1'));

/**
 * 默认应用名称（支持自定义），如果设置为空（""），则不使用多应用模式
 */
!defined('DEFAULT_APP_NAME') && define('DEFAULT_APP_NAME', 'home');

//注册类加载器
Loader::register();

//配置错误及异常处理
set_error_handler(array('\Library\AppException', 'onError'), APP_DEBUG_LEVEL);
set_exception_handler(array('\Library\AppException', 'onException'));

/**
 * 系统加载器
 * 
 * @package default
 */
class Loader {
    
    // 类名映射
    protected static $map = [];
    // 加载列表
    protected static $cacheFiles = [];
    // PSR-4
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4    = [];
    // PSR-0
    private static $prefixesPsr0 = [];
    // 是否使用composer
    private static $useComposer=false;
    
    /**
     * 注册自动加载机制
     */
    public static function register(){
        // 判断是否使用composer
        self::$useComposer=is_dir(VENDOR_PATH.'composer');
        // 注册自动加载函数
        spl_autoload_register('Loader::import');
        // 注册composer自动加载
        self::$useComposer && self::registerComposerLoader();
    }
    
    /**
     * 系统类加载
     * 
     * @param string $path 类路径
     * @return string
     */
    public static function import($class){
        $file = null;
        $ext = '.php';
        if (isset(self::$map[$class])) {
            // 从映射查找
            $file = self::$map[$class];
        }else{
            // 本地系统查找
            $path = str_replace('\\', DS, $class);
            if (strpos($path, DS)) {
                $libExt = '.class'.$ext;
                if (strpos($path, 'App' . DS) === 0) {
                    if (defined('DEFAULT_APP_NAME') && DEFAULT_APP_NAME === '') {
                        $path = substr($path, 4);
                    } else {
                        $pos = strpos($path, DS, 4);
                        $path = strtolower(substr($path, 4, $pos - 4)) . substr($path, $pos);
                    }
                    $isLib=strpos($path, 'Library'.DS)!==false;
                    $file = APP_PATH . $path . ( $isLib ? $libExt : $ext);
                } else if (strpos($path, 'Vendor' . DS) === 0) {
                    $file = VENDOR_PATH . substr($path, 7) . $ext;
                } else {
                    $file = KERNEL_PATH . $path . $libExt;
                }
            }
        }
        
        // 加载文件
        if (
            (!is_null($file) && is_file($file)) || 
            (self::$useComposer && ($file = self::findFileInComposer($class, $ext)))
        ) {
            APP_DEBUG && self::$cacheFiles[] = $file;
            include $file;
        }
    }
    
    /**
     * 注册classmap
     *
     * @param array|string $class 
     * @param string $map
     */
    public static function addMap($class, $map = ''){
        if (is_array($class)) {
            self::$map = array_merge(self::$map, $class);
        } else {
            self::$map[$class] = $map;
        }
    }
    
    /**
     * 注册composer自动加载
     */
    private static function registerComposerLoader(){
        if (is_file(VENDOR_PATH . 'composer/autoload_namespaces.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                self::$prefixesPsr0[$namespace[0]][$namespace] = (array) $path;
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_psr4.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $length = strlen($namespace);
                if ('\\' !== $namespace[$length - 1]) {
                    throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
                }
                self::$prefixLengthsPsr4[$namespace[0]][$namespace] = $length;
                self::$prefixDirsPsr4[$namespace] = (array) $path;
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_classmap.php')) {
            $classMap = require VENDOR_PATH . 'composer/autoload_classmap.php';
            if ($classMap) {
                self::addMap($classMap);
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_files.php')) {
            $includeFiles = require VENDOR_PATH . 'composer/autoload_files.php';
            foreach ($includeFiles as $fileIdentifier => $file) {
                if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                    require $file;
                    $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
                }
            }
        }
    }
    
    /**
     * 从composer中查找文件
     *
     * @param string $class
     * @param string $ext
     * @return string
     */
    private static function findFileInComposer($class, $ext = '.php'){
        // PSR-4 lookup
        $logicalPathPsr4 = strtr($class, '\\', DS) . $ext;
        
        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (is_file($file = $dir . DS . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }
        // PSR-0 lookup
        if (false !== ($pos = strrpos($class, '\\'))) {
            // namespaced
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DS);
        } else {
            // PEAR-like
            $logicalPathPsr0 = strtr($class, '_', DS) . $ext;
        }
        
        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (is_file($file = $dir . DS . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }
        // 记录不存在的类
        return self::$map[$class] = false;
    }
    

    /**
     * 加载并运行应用程序对象
     * 
     * @param object $request 外部Request对象（可选）
     * @param object $response 外部Response对象（可选）
     * @return \Library\Application 应用程序对象
     */
    public static function app($request = null, $response = null){
        $app = new Library\Application($request, $response);
        return $app->start();
    }

    /**
     * 系统环境变量加载
     * 
     * @param string $key 环境变量键名，如果为null则重写设置日志
     * @param string $value 环境变量键值
     * @param string $default 默认值
     * @return mixed
     */
    public static function env($key = null, $value = null, $default = null){
        if (is_null($key)) {
            $path = KERNEL_PATH . '..' . DS . '.env';
            if (is_file($path) && is_array($result = parse_ini_file($path))) {
                $_ENV = array_merge($_ENV, array_change_key_case($result, CASE_UPPER));
            }
            return $_ENV;
        } else if (!is_null($value)) {
            $_ENV[strtoupper($key)] = $value;
            return $value;
        }
        $key = strtoupper($key);
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * 控制器加载
     *
     * @param string $name 控制器名称（可以指定模块，以“.”分割：“模块名.控制器”）
     * @param array|false $parameters 参数列表， 如果为false则不实例化控制器，直接返回控制器类名
     * @param \Library\Container $container 使用的容器对象
     * @return \Library\Controller
     */
    public static function controller($name, $parameters = null, $container = null){
        if ($pos = strpos($name, '.', 1)) {
            $m = substr($name, 0, $pos);
            $c = substr($name, $pos + 1);
        } else {
            $m = env('ROUTE_M');
            $c = $name;
        }
        $c = str_replace('/', '\\', trim($c, '\\/.'));

        //将以下划线分割的控制器（或分组名）转化为首字母大写的驼峰式
        $ces = explode('\\', $c);
        foreach ($ces as &$v) {
            $v = ucfirst(parse_name($v, 1));
        }
        $c = implode('\\', $ces);

        $concrete = str_replace('\\\\', '\\', 'App\\' . ucfirst(strtolower($m)) . '\\Controller\\' . $c);
        if($parameters===false){
            return $concrete;
        }
        if (is_null($container)) {
            $container = \Library\Container::getInstance();
        }
        try {
            $controller = $container->make($concrete, (array)$parameters);
            $controller->setContext($container);
            return $controller;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 函数库加载
     *
     * @param string $func 函数库名
     * @param string|mixed $moudule 当是字符串为模型名称，如果是true，则为当前模型
     * @return boolean
     */
    public static function helper($name, $module = null){
        static $helpers = [];
        $baseDir = (empty($module) ? KERNEL_PATH : 
                    APP_PATH . (
                        is_string($module) ? 
                        strtolower($module) : 
                        env('ROUTE_M', '')
                    ) . DS
                );
        $path = str_replace(DS . DS, DS, $baseDir . 'Helper' . DS . $name . '.php');
        $key = md5($path);
        if (isset($helpers[$key])) {
            return true;
        }
        if (is_file($path)) {
            try {
                include_once $path;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            $helpers[$key] = false;
            return false;
        }
        $helpers[$key] = true;
        return true;
    }

    /**
     * 配置文件加载
     *
     * @param string|array $name 配置文件名称（不包括扩展名），如果为数组则设置配置选项
     * @param string $key 要获取的配置键值
     * @param string $default 默认配置，当获取配置项目失败时该值发生作用
     * @return mixed
     */
    public static function config($name, $key = '', $default = ''){
        static $appConfigs = []; //应用缓存
        static $globalConfigs = []; //全局缓存
		
        // 应用名称
        $appName = env('ROUTE_M', env('BIND_MODULE', '/'));
        if($appName===null || $appName===''){
            $appName='/';
        }
		
        // 如果为第二个参数为数组则直接写入配置
        if (is_array($key)) {
            $appConfigs[$appName][$name] = (isset($appConfigs[$appName][$name]) ? 
                                    array_merge($appConfigs[$appName][$name], $key) : $key
                                );
            return $appConfigs[$appName][$name];
        }
        
        // 加载全局配置
        if (
            !isset($globalConfigs[$name]) &&
            is_file($globalPath = STORAGE_PATH . 'Conf' . DS . $name . '.php')
        ) {
            $globalConfigs[$name] = include($globalPath);
        }
        
        // 加载应用配置
        if (
            !isset($appConfigs[$appName][$name]) &&
            is_file($appPath = simplify_ds(APP_PATH . $appName . DS . 'Conf' . DS . $name . '.php'))
        ) {
            $moduleConfig = include($appPath);
            $appConfigs[$appName][$name] = is_array($moduleConfig) ? $moduleConfig : null;
        }
        
        // 优先从应用配置中读取，如果不存在则从全局配置读取
        return $key !== '' ? (isset($appConfigs[$appName][$name][$key]) ?
                            $appConfigs[$appName][$name][$key] : (
                                isset($globalConfigs[$name][$key]) ? 
                                $globalConfigs[$name][$key] : $default
                            )
                        ) : (isset($appConfigs[$appName][$name]) ?
                                $appConfigs[$appName][$name] : 
                                (isset($globalConfigs[$name]) ? 
                                    $globalConfigs[$name] : 
                                    $default
                                )
                        );

    }

}
