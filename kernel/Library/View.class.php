<?php
namespace Library;
class View{
	protected $tVar=[]; //模板输出变量
	protected $theme=''; //模板主题
	protected static $_m=''; //默认模块
	protected static $_c=''; //默认控制器
	protected static $_a=''; //默认方法
	protected static $isInCalled=false; //是否在内部调用
	
	/**
	 * 设置默认的模块、控制器和方法
	 * @param string $m 模块名称
	 * @param string $c 控制器名称
	 * @param string $a 方法名称
	 * */
	public function setMca($m,$c,$a){
		self::$_m=$m;
		self::$_c=$c;
		self::$_a=$a;
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
	 * @param string $name
	 * @return mixed
	 */
	public function get($name=''){
		if('' === $name){
			return $this->tVar;
		}
		return isset($this->tVar[$name]) ? $this->tVar[$name] : false;
	}

	/**
	 * 加载模板和页面输出 可以返回输出内容
	 *
	 * @access public
	 * @param string $templateFile 模板文件名
	 * @param string $charset 模板输出字符集
	 * @param string $contentType 输出类型
	 * @param string $content 模板输出内容
	 * @param string $prefix 模板缓存前缀
	 * @return mixed
	 */
	public function display($templateFile='',$charset='',$contentType='',$content=''){
		// 解析并获取模板内容
		$content=$this->fetch($templateFile, $content);
		// 输出模板内容
		self::render($content, $charset, $contentType);
	}

	/**
	 * 解析和获取模板内容 用于输出
	 *
	 * @access public
	 * @param string $templateFile 模板文件名
	 * @param string $content 模板输出内容
	 * @return string
	 */
	public function fetch($templateFile='',$content=''){
		if(empty($content)){
			$res=!is_file($templateFile) ? self::resolvePath($templateFile) : $templateFile;
			fastlog($res);
			$templateFile=is_array($res) ? template($res['a'], $res['c'], $res['style'], $res['m']) : $res;
			unset($res);
			// 模板文件不存在直接返回
			
			if(!is_file($templateFile)){
				return null;
			}
		}
		// 页面缓存
		ob_start();
		ob_implicit_flush(0);
		$_content=$content;
		// 模板阵列变量分解成为独立变量，如果为数字索引，则加前缀“_”
		extract($this->tVar, EXTR_OVERWRITE|EXTR_PREFIX_INVALID,'_');
		$this->tVar=[];
		// 直接载入PHP模板
		empty($_content) ? include $templateFile : eval('?>' . $_content);
		// 获取并清空缓存
		$content=ob_get_clean();
		// 输出模板文件
		return $content;
	}

	/**
	 * 自动定位模板文件
	 *
	 * @access protected
	 * @param string $template 模板文件规则
	 * @return string
	 */
	public static function resolvePath($template=''){
		$a=ltrim(empty(self::$_a) && env('ROUTE_A',false) ? env('ROUTE_A') : self::$_a,'_');
		$c=empty(self::$_c) && env('ROUTE_C',false) ? env('ROUTE_C') : self::$_c;
		$m=empty(self::$_m) && env('ROUTE_M',false) ? env('ROUTE_M') : self::$_m;
		$depr=defined('TAGLIB_DEPR') ? TAGLIB_DEPR : C('TAGLIB_DEPR', '/');
		$template=rtrim(str_replace(':', $depr, $template), $depr . '@');
		$style='';
		if($pos=strpos($template, '@')){
			$sm=explode(':', substr($template, $pos+1),2);
			$template=substr($template, 0, $pos);
			if(!empty(trim($sm[0]))){
				$style=trim($sm[0]);  //获取风格名称，例如: Index/list@Default
			}
			if(isset($sm[1]) && !empty(trim($sm[1]))){
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
				$cas=explode($depr, trim($template,$depr));
				$a=array_pop($cas);
				
				$dcs=explode('/',$c);
				$cdcs=count($dcs);
				for($i=0; $i<$cdcs; $i++){
					if(!empty($cas)){
						$dcs[$cdcs-$i-1]=array_pop($cas);
					}else{
						break;
					}
				}
				$c=implode('/', $dcs);
			}
		}
		
		return ['a'=>$a,'c'=>$c,'style'=>$style,'m'=>$m];
	}
	
	/**
	 * 设置是否内部调用
	 * @param bool $isInCalled 是否在内部调用
	 */
	public static function setInCalled($isInCalled){
		self::$isInCalled=$isInCalled;
	}
	
	/**
	 * 输出内容文本可以包括Html
	 *
	 * @access private
	 * @param string $content 输出内容
	 * @param string $charset 模板输出字符集
	 * @param string $contentType 输出类型
	 * @return mixed
	 */
	public static function render($content,$charset='',$contentType=''){
		$response=make(Response::class);
		if(!$response->hasSendHeader()){
			if(empty($charset) || !is_string($charset)){
				$charset=C('charset', 'utf-8');
			}
			if(empty($contentType) || !is_string($contentType)){
				$type=is_array($content) || is_object($content) ? 'json' : 'html';
				$contentType=C('mimetype.'.$type,'text/html');
			}
			$response->header('Content-Type',$contentType . '; charset=' . $charset); // 网页字符编码
			$response->header('Cache-control',C('HTTP_CACHE_CONTROL', 'private')); // 页面缓存控制
			$response->header('X-Powered-By','steeze');
		}
		
		//输出内容
		!is_null($content) && 
			$response->write($content,self::$isInCalled);
	}
}