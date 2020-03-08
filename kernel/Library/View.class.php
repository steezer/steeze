<?php

namespace Library;

use Exception;
use Loader as load;
use Service\Template\Manager as TemplateManager;

/**
 * 系统视图类
 * 
 * @package Library
 */
final class View
{
    /**
     * 模板输出变量
     *
     * @var array
     */
    protected $tVar = array();

    private static $_m = ''; //默认模块
    private static $_c = ''; //默认控制器
    private static $_a = ''; //默认方法

    /**
     * 应用上下文对象
     *
     * @var Application
     */
    private $context = null;

    /**
     * 分页对象
     *
     * @var Pager
     */
    private $pager = null;

    /**
     * 设置应用上下文对象
     *
     * @param Application $context
     */
    public function __construct(Application $context)
    {
        if ($context instanceof Application) {
            $this->context = $context;
        } else {
            throw new Exception(L('You should create view in Application context!'));
        }
    }

    /**
     * 获取应用上下文对象
     *
     * @return Application
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * 获取列表分页
     * @param array $config 分页信息参数配置
     * @param int $setPages 显示页数（可选），默认：10
     * @param string $urlRule 包含变量的URL规则模板（可选），默认：{type}={page}
     * @param array $array 附加的参数（可选）
     * @return array 分页配置，包括html和info字段
     */
    public function getPager($config = array(), $setPages = 10, $urlRule = '', $array = array())
    {
        if (is_null($this->pager)) {
            $this->pager = new Pager();
        }
        if (!isset($config['url']) && !is_null($this->context)) {
            $config['url'] = $this->context->getRequest()->server('request_uri', '/');
        }
        return $this->pager->getPager($config, $setPages, $urlRule, $array);
    }

    /**
     * 模板变量赋值
     *
     * @access public
     * @param mixed $name
     * @param mixed $value
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->tVar = array_merge($this->tVar, $name);
        } else {
            $this->tVar[$name] = $value;
        }
    }

    /**
     * 取得模板变量的值
     *
     * @access public
     * @param string $name 变量名称，默认获取所有模板变量
     * @param boolean $isClear 输出所有变量后，是否清理模板变量
     * @return mixed
     */
    public function get($name = '', $isClear = true)
    {
        if ('' === $name) {
            if ($isClear) {
                $result = $this->tVar;
                $this->tVar = array();
                return $result;
            } else {
                return $this->tVar;
            }
        }
        return isset($this->tVar[$name]) ? $this->tVar[$name] : false;
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     *
     * @param string $file 需要渲染的文件名
     * @param string|array $data 渲染输出的内容，如果为空字符串则使用文件渲染，如果为数组则为模板变量
     * @return bool 成功输出返回true，如果模版未找到返回false
     */
    public function display($file = '', $data = '')
    {
        // 解析并获取模板内容
        $content = $this->fetch($file, $data);
        // 输出模板内容
        $this->getContext()->getResponse()
            ->write($content);
        return !is_null($content);
    }

    /**
     * 解析和获取模板内容 用于输出
     *
     * @param string $file 需要渲染的文件名
     * @param string|array $data 渲染输出的内容，如果为空字符串则使用文件渲染，如果为数组则为模板变量
     * @return string
     */
    public function fetch($file = '', $data = '')
    {
        if (is_array($data)) {
            $this->assign($data);
            $data = '';
        }
        return $data==='' ? $this->fetchFile($file) : 
                $this->fetchString($data);
    }
    
    /**
     * 从字符串模版渲染
     *
     * @param string $str 模版字符串
     * @param array $data 参数数据
     * @return string
     */
    public function fetchString($str, $data=null)
    {
        // 赋值模版变量
        !is_null($data) && 
            $this->assign((array)$data);
        
        // 解析字符串
        $str=TemplateManager::instance()->parse($str);
        
        return $this->parse($str, false);
    }
    
    /**
     * 从模版文件渲染
     *
     * @param string $file 模版文件
     * @param array $data 参数数据
     * @return string
     */
    public function fetchFile($file, $data=null)
    {
        // 解析模版文件
        $res = !is_file($file) ? self::resolvePath($file) : $file;
        $file = is_array($res) ? template($res['a'], $res['c'], $res['style'], $res['m']) : $res;
        unset($res);
        
        // 模板文件不存在直接返回
        if (!is_file($file)) {
            return null;
        }
        // 赋值模版变量
        !is_null($data) && 
            $this->assign((array)$data);
        
        return $this->parse($file, true);
    }
    
    /**
     * 解析文件或字符串
     *
     * @param string $fileOrString 文件路径或字符串
     * @param boolean $isFile 是否为文件，默认
     * @return string
     */
    private function parse($fileOrString, $isFile=false)
    {
        // 开始页面缓存
        ob_start();
        ob_implicit_flush(0);
        // 模板阵列变量分解成为独立变量，如果为数字索引，则加前缀“_”
        extract($this->tVar, EXTR_OVERWRITE | EXTR_PREFIX_INVALID, '_');
        $this->tVar=array();
        
        if($isFile){
            include $fileOrString;
        }else{
            if(function_exists('eval')){
                eval('?>' . $fileOrString);
            }else{
                $___filename__=CACHE_PATH.'tpl_'.md5($fileOrString).'.php';
                if(file_put_contents($___filename__, $fileOrString)){
                    include $___filename__;
                }
                unlink($___filename__);
            }
        }
        // 清空缓存并返回
        return ob_get_clean();
    }

    /**
     * 渲染输出控制器方法的返回值
     *
     * @param Controller $concrete
     * @param string $method
     * @param array $param
     * @return mixed
     */
    public function render($concrete, $method, array $param = array())
    {
        $context = $this->getContext();
        if (!is_null($context)) {
            if (
                (is_object($concrete) && $concrete instanceof Controller) ||
                is_string($concrete)
            ) {
                if (is_string($concrete)) {
                    $concrete = load::controller($concrete, $param, $context);
                    if (is_null($concrete)) {
                        return null;
                    }
                }
                // 获取控制器类名
                $classname = get_class($concrete);
                // 记录控制器的调用信息
                $classes = explode('\\', $classname);
                array_shift($classes);
                $cm = array_shift($classes);
                if ($cm != 'Controller') {
                    self::$_m = strtolower($cm);
                    array_shift($classes);
                } else {
                    self::$_m = '';
                }
                self::$_c = implode('/', $classes);
                self::$_a = $method;
                
                if(method_exists($concrete, $method)){
                    return $context->invokeMethod($concrete, $method, $param);
                }
                
                if(is_callable(array($concrete, $method))){
                    return call_user_func_array(array($concrete, $method), $param);
                }
                
            }
        }
        return null;
    }

    /**
     * 自动定位模板文件
     *
     * @access protected
     * @param string $template 模板文件规则
     * @return string
     */
    public static function resolvePath($template = '', $defaultA=null, $defaultC=null, $defaultM=null)
    {
        $a = !is_null($defaultA) ? $defaultA : (empty(self::$_a) && env('ROUTE_A', false) ? env('ROUTE_A') : self::$_a);
        $c = !is_null($defaultC) ? $defaultC : (empty(self::$_c) && env('ROUTE_C', false) ? env('ROUTE_C') : self::$_c);
        $m = !is_null($defaultM) ? $defaultM : (empty(self::$_m) && env('ROUTE_M', false) ? env('ROUTE_M') : self::$_m);
        $depr = defined('TAGLIB_DEPR') ? constant('TAGLIB_DEPR') : C('TAGLIB_DEPR', '/');
        $template = rtrim(str_replace(':', $depr, $template), $depr . '@');
        $style = '';
                
        // 不使用命名控件使用小写目录
        if(!USE_NAMESPACE){
            $c=strtolower($c);
        }
        
        if ($pos = strpos($template, '@')) {
            $sm = explode(':', substr($template, $pos + 1), 2);
            $template = substr($template, 0, $pos);
            $sm[0] = trim($sm[0]);
            if (!empty($sm[0])) {
                $style = $sm[0];  //获取风格名称，例如: Index/list@Default
            }
            $sm[1] = isset($sm[1]) ? trim($sm[1]) : '';
            if (!empty($sm[1])) {
                $m = trim($sm[1]);  //获取模块名称，例如: Index/list@Default:home
            }
        }

        if ($template !== '') {
            $dpos = strpos($template, $depr);
            if ($dpos === false) { //只有方法名，使用默认控制器，例如: "a"
                $a = $template;
            } elseif ($dpos === 0) {
                //绝对路径，重写类名，例如: "/" 或 "/c/a" 或 "/g/c/a"
                $cas = explode($depr, trim($template, $depr));
                $a = array_pop($cas);
                $c = implode('/', $cas);
            } else { //相对路径，相对于分组，例如: "c/a" 或 "g/c/a"
                $dcs = explode('/', $c);

                //统计上级目录数量
                $up_dir = '..' . $depr;
                $up_count = min(substr_count($template, $up_dir), count($dcs)) + 1;

                $cas = explode($depr, trim(str_replace($up_dir, $depr, $template), $depr));
                $a = array_pop($cas);

                array_splice($dcs, -$up_count, $up_count, $cas);

                $c = implode('/', $dcs);
            }
        }

        return array('a' => $a, 'c' => $c, 'style' => $style, 'm' => $m);
    }
}
