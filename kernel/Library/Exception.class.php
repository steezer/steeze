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
	static public function report(\Exception $e){
		if(C('errorlog')){
			$content='('.$e->getCode().')'.$e->getMessage();
			$content.=' in '.str_replace(dirname(KERNEL_PATH),'',$e->getFile()).'['.$e->getLine().']';
			fastlog($content,true,'exception.log');
		}
	}
	
	/*
	 * 错误渲染
	 * @param \Exception|\Error $e 错误对象
	 * @param array $params 传递的参数
	 * @param bool $isReturn 是否返回渲染后的异常模版，否则直接输出
	 * @return string|void
	 */
	static public function render($e,array $params=[],$isReturn=false){
		if(env('PHP_SAPI','cli')=='cli'){ //命令行模式运行
			$data='('.$e->getCode().')'.$e->getMessage()."\n";
			$data.='File: '.str_replace(dirname(ROOT_PATH), '', $e->getFile()).'['.$e->getLine()."]\n";
			if($isReturn){
				return $data;
			}
			echo $data;
		}else if((defined('IS_AJAX') && IS_AJAX)){ //ajax模式运行
			$data=json_encode([
						'code'=>$e->getCode(),
						'message'=>$e->getMessage(),
						'line'=> $e->getLine(),
						'file'=> str_replace(dirname(ROOT_PATH), '', $e->getFile()),
					],JSON_UNESCAPED_UNICODE);
			if($isReturn){
				return $data;
			}
			echo $data;
		}else if(is_file($tpl=C('tmpl_exception_tpl'))){  //web模式运行
			//直接访问模版
			$viewer=new View();
			$viewer->assign($params);
			$viewer->assign('e',$e);
			if($isReturn){
				return $viewer->fetch($tpl);
			}
			$viewer->display($tpl);
		}
	}
}