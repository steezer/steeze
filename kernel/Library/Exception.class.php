<?php
namespace Library;
/**
 * ThinkPHP系统异常基类
 */
class Exception extends \Exception {
	/*
	 * 错误报告
	 * @param Exception $e
	 */
	public function report(\Exception $e){
		if(C('errorlog')){
			$content='('.$e->getCode().')'.$e->getMessage();
			$content.=' in '.str_replace(dirname(KERNEL_PATH),'',$e->getFile()).'['.$e->getLine().']';
			fastlog($content,true,'exception.log');
		}
	}
	
	/*
	 * 错误输出
	 */
	public function render(\Exception $e,...$params){
		//获取路由参数
		if(is_file($tplfile=C('tmpl_exception_file'))){
			//直接访问模版
			extract($params);
			include $tplfile;
		}
	}
}