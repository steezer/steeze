<?php

namespace Library;

use Loader as load;

/**
 * 系统路由类
 * 
 * @package Library
 */
class Route
{
    static private $middlewares = array(); //中间件数组
    private $params = array(); //绑定的路由参数
    private $disposer = null; //请求处理器
    private $request = null; //请求对象

    public function __construct(Request $request)
    {
        //初始化中间件栈
        self::$middlewares = array();
        //设置路由请求对象
        $this->request = $request;
    }

    /*
	 * 获取路由匹配参数
	 * @param string $name 参数名称 如果为null，则返回参数数组
	 * return string|array
	 */
    public function getParam($name = null)
    {
        return is_null($name) ? $this->params : $this->params[$name];
    }

    /*
	 * 设置绑定的控制器
     * 
	 * @return Controller|Closure
	 */
    public function setDisposer($disposer)
    {
        $this->disposer = $disposer;
    }

    /*
	 * 获取绑定的控制器
     * 
	 * @return Controller|Closure
	 */
    public function getDisposer()
    {
        return $this->disposer;
    }

    /*
	 * 检查路由参数是否匹配
     * 
     * @param string $path 访问路径
     * @param string $host 服务器名称
	 */
    public function bind($path, $host)
    {
        //使用路径参数匹配
        $handle = $this->matchHandle($path, $host);
        $route_m = $route_c = $route_a = null;
        if (is_null($handle) || is_string($handle)) {
            //获取路由处理器，如：index/show@home
            if (is_string($handle)) {
                $res = explode('@', $handle);
                $cas = explode('/', array_shift($res));
                $route_a = array_pop($cas);
                !empty($cas) && ($route_c = implode('/', $cas));
                !empty($res) && ($route_m = strtolower(array_pop($res)));
            }

            //设置默认路由常量，同时使用传统路由方式匹配模式
            if ($path == env('ROOT_URL') || USE_DEFUALT_HANDLE) {
                !isset($route_c) && ($route_c = defined('BIND_CONTROLLER') ?
                        BIND_CONTROLLER : ucfirst($this->request->input(C('var_controller', 'c'), C('default_c'))));
                !isset($route_a) && ($route_a = defined('BIND_ACTION') ?
                        BIND_ACTION : $this->request->input(C('var_action', 'a'), C('default_a')));
            }
        } else if (is_callable($handle)) {
            $this->setDisposer($handle);
        }

        //绑定方法
        load::env('ROUTE_A', (isset($route_a) ? $route_a : false));
        //绑定控制器
        if (isset($route_c)) {
            $ces = explode('/', str_replace('\\', '/', $route_c));
            foreach ($ces as &$v) {
                $v = ucfirst(parse_name($v, 1));
            }
            load::env('ROUTE_C', implode('/', $ces));
        } else {
            load::env('ROUTE_C', false);
        }
        //绑定模块
        load::env('ROUTE_M', (isset($route_m) ? $route_m : env('BIND_MODULE')));
    }

    /*
	 * 查找路由处理器
	 * @param string $path URL地址
	 * @param string $host 主机名称
	 * @return string|null
	 */
    private function matchHandle($path, $host)
    {
        $configs = C('route.*', array());
        $default = isset($configs['default']) ? $configs['default'] : array();
        unset($configs['default']);
        //通过主机名获取路由配置
        $routes = $this->getRoutesByHost($host, $configs);
        if (is_null($routes)) {
            $routes = $default;
        }
        unset($configs, $default);

        //对URL访问路径进行路由匹配
        foreach ($routes as $key => $value) {
            if (is_array($value)) {
                $arrs = explode(':', $key);
                count($arrs) == 1 && array_unshift($arrs, 'ANY');
                $method = strtoupper($arrs[0]);
                if ($method == 'ANY' || $method == env('REQUEST_METHOD')) {
                    foreach ($value as $k => $v) {
                        if (!is_null($result = $this->getHandle($path, $k, $v))) {
                            !empty($arrs[1]) && self::setMiddleware(explode('&', $arrs[1]));
                            return $result;
                        }
                    }
                }
            } elseif (!is_null($result = $this->getHandle($path, $key, $value))) {
                return $result;
            }
        }
        return null;
    }

    /*
	 * 根据主机名称获取路由配置信息
	 * @param string $host 域名
	 * @param array &$configs 所有路由配置
	 * @return 匹配的路由配置
	 */
    private function getRoutesByHost($host, &$configs)
    {
        static $cacheHosts = array(); //主机模块缓存
        static $cacheRoutes = array(); //主机路由缓存

        //自动为不带子域名的主机名称带上"www."前缀
        if (substr_count($host, '.') == 1) {
            $host = 'www.' . $host;
        }

        if (!isset($cacheHosts[$host])) {
            $routes = array();
            $routePath=STORAGE_PATH . 'Routes';
            //从总配置文件和分布文件读取
            if (isset($configs[$host])) {
                $routes = $configs[$host];
            }
            $file = $routePath . DS . $host . '.php';
            if (is_file($file) && is_array($confs = include($file))) {
                $routes = array_merge($routes, $confs);
            }

            //尝试从泛解析域名读取，例如：*.steeze.cn
            if (empty($routes) && strpos($host, '.')) {
                $domain = '*' . strstr($host, '.');
                if (isset($configs[$domain])) {
                    $routes = $configs[$domain];
                }
                $file = $routePath . DS . $domain . '.php';
                if (is_file($file) && is_array($confs = include($file))) {
                    $routes = array_merge($routes, $confs);
                }
            }

            //从绑定模块的路由中获取，如：home@*.h928.com
            $bindModule = '';
            $cDomain = null;
            if (empty($routes)) {
                //从全局配置中查找
                $domains = array_keys($configs);
                foreach ($domains as $domain) {
                    $cRoutes = explode('@', $domain);
                    $cDomain = array_shift($cRoutes);
                    if (
                        $host == $cDomain || (strpos($cDomain, '*.') === 0 && $cDomain == '*' . strstr($host, '.'))
                    ) {
                        $routes = $configs[$domain];
                        if (!empty($cRoutes)) {
                            $bindModule = array_shift($cRoutes);
                        }
                        break;
                    }
                }

                //如果在全局中未找到，则从路由配置目录中查找
                if (empty($routes)) {
                    if (is_dir($routePath) && ($handle = opendir($routePath))) {
                        while (false !== ($file = readdir($handle))) {
                            if ($file != '.' && $file != '..' && is_file($routePath . DS . $file)) {
                                $domain = basename($file, '.php');
                                $cRoutes = explode('@', $domain);
                                $cDomain = array_shift($cRoutes);
                                if (
                                    $host == $cDomain || (strpos($cDomain, '*.') === 0 && $cDomain == '*' . strstr($host, '.'))
                                ) {
                                    $routes = include($routePath . DS . $file);
                                    if (!empty($cRoutes)) {
                                        $bindModule = array_shift($cRoutes);
                                    }
                                    break;
                                }
                            }
                        }
                        closedir($handle);
                    }
                }
            }

            $cacheHosts[$host] = $bindModule;
            if (!empty($routes)) {
                $cacheRoutes[(isset($cDomain) ? $cDomain : $host)] = $routes;
            }
        }

        //绑定系统应用模块
        load::env(
            'BIND_MODULE',
            strtolower(
                !empty($cacheHosts[$host]) ?
                    $cacheHosts[$host] : (USE_DEFUALT_HANDLE ?
                        $this->request->input(C('var_module', 'm'), DEFAULT_APP_NAME) : DEFAULT_APP_NAME)
            )
        );

        $sHost = '*' . strstr($host, '.');
        return isset($cacheRoutes[$host]) ? $cacheRoutes[$host] : (isset($cacheRoutes[$sHost]) ? $cacheRoutes[$sHost] : null);
    }

    /**
     * 根据匹配模式从路径中获取变量
     *
     * @param string $path 路径
     * @param string $pattern 匹配模式
     * @param bool $isVar 处理器中是否包含变量
     * @param string &$handle 处理器
     * @return array|false 成功返回变量的键值，失败返回false
     */
    private function getVars($path, $pattern, $isVar, &$handle)
    {
        $index = 0;
        $start = 0;
        $end = 0;
        $prev = '';
        $key = '';
        $vepos = 0;
        while ($index > -1) {
            $start = strpos($pattern, '{', $index);
            if ($index) {
                $next = $start === false ? substr($pattern, $index) : substr($pattern, $index, $start - $index);

                $value = '';
                if ($prev === '') {
                    $vspos = 0;
                    if ($next !== '') {
                        $vepos = strpos($path, $next);
                        if ($vepos === false) {
                            return false;
                        }
                        $value = substr($path, 0, $vepos);
                    } else {
                        $value = $path;
                    }
                } else {
                    if (($vspos = strpos($path, $prev, $vepos)) !== false) {
                        $vspos += strlen($prev);
                        if ($next !== '') {
                            $vepos = strpos($path, $next, $vspos);
                            if ($vepos === false) {
                                return false;
                            }
                            $value = substr($path, $vspos, $vepos - $vspos);
                        } else {
                            $value = substr($path, $vspos);
                        }
                    }
                }

                if ($key === '') {
                    return false;
                }

                $isOptional = substr($key, -1) == '?';
                if (!$isOptional && $value === '') {
                    return false;
                }


                $kvnts = explode('|', ($isOptional ? substr($key, 0, -1) : $key));
                $kvName = $kvnts[0];
                $kvType = isset($kvnts[1]) ? $kvnts[1] : 's';
                if ($kvType == 'd') {  // 变量类型检查
                    if (is_numeric($value)) {
                        $this->params[$kvName] = $value;
                    } else {
                        return false;
                    }
                } else if ($value !== '') { //此处兼容首页参数
                    $this->params[$kvName] = $value;
                }
                if ($isVar) { // 路由控制器变量处理
                    $handle = str_replace('{' . $kvName . '}', $value, $handle);
                }
            }
            if ($start !== false) {
                $prev = substr($pattern, $index, $start - $index);
                if (($end = strpos($pattern, '}', $start)) !== false) {
                    $key = substr($pattern, $start + 1, $end - $start - 1);
                    $index = $end + 1;
                } else {
                    $index = -1;
                }
            } else {
                $index = -1;
            }
        }

        return true;
    }


    /*
	 * 获取路由处理器
	 * @param string $path URL路径
	 * @param string $route 路由
	 * @param string|function $handle 处理器 
	 * @return string|null
	 */
    private function getHandle($path, $route, $handle)
    {
        //请求方法匹配
        $routes = explode(':', $route, 2);
        $route = trim(array_pop($routes));
        $method = count($routes) ? strtoupper(array_pop($routes)) : 'ANY';
        $route = '/' . trim($route, '/');

        $middlewares = array();
        if (is_string($handle)) {
            $handles = explode('>', $handle, 2);
            $handle = trim(array_pop($handles));
            if (!empty($handles)) {
                $middlewares = array_merge($middlewares, explode('&', array_pop($handles)));
            }
        }
        $routeLen = substr_count($route, '/');
        $urlLen = substr_count($path, '/');
        $optCount = substr_count($route, '?');

        //无参数或有参数的路径匹配
        if (
            ($method == 'ANY' || $method == env('REQUEST_METHOD')) && ($routeLen == $urlLen || $urlLen + $optCount == $routeLen)
        ) {
            if (!strcasecmp($route, $path)) {
                $this->setMiddleware($middlewares);
                //如果url完全匹配（不区分大小写），直接返回
                return $handle;
            } else {
                //否则进行变量类型查找
                $kArrs = explode('/', $route);
                $urlArrs = explode('/', $path);

                $isVar = is_string($handle) && strpos($handle, '}') !== false;
                $mCount = count($kArrs);
                foreach ($kArrs as $ki => $kv) {
                    if (isset($urlArrs[$ki]) && strcasecmp($kv, $urlArrs[$ki])) {
                        /**
                         * 注意：以“/”分割的路由路径中，可以包含以特殊字符分割的多个变量，
                         * 例如可以是“/index/{c}”、“/index/show-{a}-{b}”、单不能是“/index/show-{c}{b}”
                         */
                        if (
                            strpos($kv, '{') === false ||
                            !$this->getVars($urlArrs[$ki], $kv, $isVar, $handle)
                        ) { // 变量匹配检查
                            break;
                        }
                    }
                    $mCount--;
                }
                if (!$mCount) {
                    $this->setMiddleware($middlewares);
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
    public static function setMiddleware($name, $excepts = array())
    {
        if (is_array($name)) {
            foreach ($name as $n) {
                self::setMiddleware($n, $excepts);
            }
        } else {
            $name = trim($name);
            $middlewares = C('middleware.*', array());
            if (isset($middlewares[$name])) {
                if (!isset(self::$middlewares[$name])) {
                    self::$middlewares[$name] = (array) $excepts;
                } else {
                    self::$middlewares[$name] = array_unique(array_merge(self::$middlewares[$name], (array) $excepts));
                }
            }
        }
    }

    /*
	 * 获取中间件（或根据方法名称返回可用中间件）
	 * @param string $name 方法名称
	 * @return array 中间数组
	 * 说明：如果提供方法名称，则根据方法名称返回可用中间件
	 */
    public static function getMiddleware($name = null)
    {
        $classes = array();
        $middlewares = C('middleware.*', array());
        foreach (self::$middlewares as $key => $values) {
            if (!is_null($name)) {
                if (!in_array($name, $values)) {
                    $classes[] = $middlewares[$key];
                }
            } else {
                $classes[] = $middlewares[$key];
            }
        }
        return $classes;
    }
}
