<?php
namespace Library;
class View{
	protected $tVar=[]; //模板输出变量
	protected $theme=''; //模板主题
	protected static $_m=''; //默认模块
	protected static $_c=''; //默认控制器
	protected static $_a=''; //默认方法
	
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
		$a=ltrim(empty(self::$_a) && defined('ROUTE_A') ? ROUTE_A : self::$_a,'_');
		$c=empty(self::$_c) && defined('ROUTE_C') ? ROUTE_C : self::$_c;
		$m=empty(self::$_m) && defined('ROUTE_M') ? ROUTE_M : self::$_m;
		$depr=defined('TAGLIB_DEPR') ? TAGLIB_DEPR : C('TAGLIB_DEPR', '/');
		$template=rtrim(str_replace(':', $depr, $template), $depr . '@');
		// 获取当前模块 home@aa/bb/cc
		$m=($pos=strpos($template, '@')) ? substr($template, 0, $pos) : $m;
		if($pos){
			$template=substr($template, $pos + 1);
		}
		
		$style='';
		if($template !== ''){
			$dpos=strpos($template, $depr);
			if($dpos === false){ // "a"
				$a=$template;
			}elseif($dpos === 0){
				$template=substr($template, 1);
				$dpos=strpos($template, $depr);
				if($dpos === false){ // "/a"
					$c='';
					$a=$template;
				}elseif(substr_count($template, $depr) > 1){ // eg:"/style/c/a"
					list($style, $c, $a)=explode($depr, $template);
				}else{ // eg:"/style/a"
					list($style, $a)=explode($depr, $template);
					$c='';
				}
			}elseif(substr_count($template, $depr) > 1){ // eg:"style/c/a"
				list($style, $c, $a)=explode($depr, $template);
			}else{// eg:"c/a"
				list($c, $a)=explode($depr, $template);
			}
		}
		return ['a'=>$a,'c'=>$c,'style'=>$style,'m'=>$m];
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
		if(!headers_sent()){
			if(empty($charset) || !is_string($charset)){
				$charset=C('charset', 'utf-8');
			}
			if(empty($contentType) || !is_string($contentType)){
				$contentType='text/html';
			}
			header('Content-Type:' . $contentType . '; charset=' . $charset);// 网页字符编码
			header('Cache-control: ' . C('HTTP_CACHE_CONTROL', 'private')); // 页面缓存控制
			header('X-Powered-By:STWMS');
		}
		//输出内容
		if(!is_null($content)){
			echo (is_array($content) ? json_encode($content,JSON_UNESCAPED_UNICODE) : $content);
		}
	}
}