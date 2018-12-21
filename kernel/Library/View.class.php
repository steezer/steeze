<?php
namespace Library;
use Loader;

/**
 * 系统视图类
 * 
 * @package Library
 */
final class View{
    /**
     * 模板输出变量
     *
     * @var array
     */
	protected $tVar=[];
    
	private static $_m=''; //默认模块
	private static $_c=''; //默认控制器
	private static $_a=''; //默认方法
    
    /**
     * 应用上下文对象
     *
     * @var \Library\Application
     */
    private $context=null;
    
    /**
     * 分页对象
     *
     * @var \Library\Pager
     */
	private $pager=null;
    
    /**
     * 设置应用上下文对象
     *
     * @param \Library\Application $context
     */
    public function __construct(Application $context){
        if($context instanceof Application){
            $this->context=$context;
        }else{
            E(L('You should create view in Application context!'));
        }
    }
    
    /**
     * 获取应用上下文对象
     *
     * @return \Library\Application
     */
    public function getContext(){
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
    public function getPager($config=[], $setPages=10, $urlRule='', $array=[]){
        if(is_null($this->pager)){
            $this->pager=new Pager();
        }
        if(!isset($config['url']) && !is_null($this->context)){
            $config['url']=$this->context->getRequest()->server('request_uri','/');
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
	public function assign($name,$value=''){
		if(is_array($name)){
			$this->tVar=array_merge($this->tVar, $name);
		}else{
			$this->tVar[$name]=$value;
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
	public function get($name='',$isClear=true){
		if('' === $name){
			if($isClear){
				$result = $this->tVar;
				$this->tVar = [];
				return $result;
			}else{
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
	 * @return mixed
	 */
	public function display($file='', $data=''){
		// 解析并获取模板内容
		$content=$this->fetch($file, $data);
		// 输出模板内容
        $this->getContext()->getResponse()
            ->write($content);
	}

	/**
	 * 解析和获取模板内容 用于输出
	 *
	 * @param string $file 需要渲染的文件名
	 * @param string|array $data 渲染输出的内容，如果为空字符串则使用文件渲染，如果为数组则为模板变量
	 * @return string
	 */
	public function fetch($file='', $data=''){
        if(is_array($data)){
            $this->assign($data);
            $data='';
        }
		if(empty($data)){
			$res=!is_file($file) ? self::resolvePath($file) : $file;
			$file=is_array($res) ? template($res['a'], $res['c'], $res['style'], $res['m']) : $res;
			unset($res);
			// 模板文件不存在直接返回
			
			if(!is_file($file)){
				return null;
			}
		}
		// 页面缓存
		ob_start();
		ob_implicit_flush(0);
		$_content=$data;
		// 模板阵列变量分解成为独立变量，如果为数字索引，则加前缀“_”
		extract($this->tVar, EXTR_OVERWRITE|EXTR_PREFIX_INVALID,'_');
		$this->tVar=[];
		// 直接载入PHP模板
        if(empty($_content)){
            include $file;
        }else{
            eval('?>' . $_content);
        }
		// 获取并清空缓存
		$data=ob_get_clean();
		// 输出模板文件
		return $data;
	}
    
    /**
     * 渲染输出控制器方法的返回值
     *
     * @param \Library\Controller $concrete
     * @param string $method
     * @param array $param
     * @return mixed
     */
    public function render($concrete, $method, array $param=[]){
        $context=$this->getContext();
        if(!is_null($context)){
            if(
                (is_object($concrete) && $concrete instanceof Controller) || 
                is_string($concrete)
            ){
                if(is_string($concrete)){
                    $concrete=Loader::controller($concrete, $param, $context );
                    if(is_null($concrete)){
                        return null;
                    }
                }
                // 获取控制器类名
                $classname=get_class($concrete);
                // 记录控制器的调用信息
                $classes=explode('\\', $classname);
                array_shift($classes);
                $cm=array_shift($classes);
                if($cm!='Controller'){
                    self::$_m=strtolower($cm);
                    array_shift($classes);
                }else{
                    self::$_m='';
                }
                self::$_c=implode('/',$classes);
                self::$_a=$method;
        
                return $context->invokeMethod($concrete, $method, $param);
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
	public static function resolvePath($template=''){
		$a=empty(self::$_a) && env('ROUTE_A',false) ? env('ROUTE_A') : self::$_a;
		$c=empty(self::$_c) && env('ROUTE_C',false) ? env('ROUTE_C') : self::$_c;
		$m=empty(self::$_m) && env('ROUTE_M',false) ? env('ROUTE_M') : self::$_m;
		$depr=defined('TAGLIB_DEPR') ? TAGLIB_DEPR : C('TAGLIB_DEPR', '/');
		$template=rtrim(str_replace(':', $depr, $template), $depr . '@');
		$style='';
		if($pos=strpos($template, '@')){
			$sm=explode(':', substr($template, $pos+1),2);
			$template=substr($template, 0, $pos);
			$sm[0]=trim($sm[0]);
			if(!empty($sm[0])){
				$style=$sm[0];  //获取风格名称，例如: Index/list@Default
			}
			$sm[1]=isset($sm[1]) ? trim($sm[1]) : '' ;
			if(!empty($sm[1])){
				$m=trim($sm[1]);  //获取模块名称，例如: Index/list@Default:home
			}
		}
		
		if($template !== ''){
			$dpos=strpos($template, $depr);
			if($dpos === false){ //只有方法名，使用默认控制器，例如: "a"
				$a=$template;
			}elseif($dpos === 0){
				//绝对路径，重写类名，例如: "/" 或 "/c/a" 或 "/g/c/a"
				$cas=explode($depr, trim($template,$depr));
				$a=array_pop($cas);
				$c=implode('/', $cas);
			}else{//相对路径，相对于分组，例如: "c/a" 或 "g/c/a"
				$dcs=explode('/',$c);

				//统计上级目录数量
				$up_dir='..'.$depr;
				$up_count=min(substr_count($template,$up_dir),count($dcs))+1;

				$cas=explode($depr, trim(str_replace($up_dir,$depr,$template),$depr));
				$a=array_pop($cas);

				array_splice($dcs, -$up_count, $up_count, $cas);

				$c=implode('/', $dcs);
			}
		}
		
		return ['a'=>$a,'c'=>$c,'style'=>$style,'m'=>$m];
	}
	
}