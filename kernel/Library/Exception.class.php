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
	 * @param bool $isReturn 是否返回渲染后的异常模板，否则直接输出
	 * @return string|void
	 */
	static public function render($e,array $params=[],$isReturn=false){
		$error=[
			'url'=>make('\Library\Request')->server('REQUEST_URI'),
			'code'=>$e->getCode(),
			'message'=>$e->getMessage(),
			'line'=> $e->getLine(),
			'file'=> str_replace(dirname(ROOT_PATH), '', $e->getFile()),
		];
		//将错误写入日志
		fastlog(json_encode($error,JSON_UNESCAPED_UNICODE),true,'exception.log');
		
		if(env('PHP_SAPI','cli')=='cli'){ //命令行模式运行
			$data='('.$error['code'].')'.$error['message']."\n";
			$data.='File: '.$error['file'].'['.$error['line']."]\n";
			if($isReturn){
				return $data;
			}
			make('\Library\Response')->write($data);
		}else if(env('IS_AJAX',false)){ //ajax模式运行
			$data=json_encode($error,JSON_UNESCAPED_UNICODE);
			if($isReturn){
				return $data;
			}
			make('\Library\Response')->write($data);
		}else if(is_file($tpl=C('tmpl_exception_tpl'))){  //web模式运行
			//直接访问模板
			$viewer=make('\Library\View');
			$viewer->assign($params);
			$viewer->assign('e',$e);
			if($isReturn){
				return $viewer->fetch($tpl);
			}
			$viewer->display($tpl);
		}
	}
}