<?php
namespace Library;
use Exception;

/**
 * 错误异常处理类
 * @package Library
 */
class AppException extends Exception {
	
	//默认错误代码
	const DEFAULT_ERROR_CODE = -101;
    
    /**
     * 上下文对象
     *
     * @var Application
     */
    private $context=null;
    
    /**
     * 设置上下文应用对象
     *
     * @param Application &$context
     * @return void
     */
    public function setContext(&$context){
        $this->context=$context;
    }
    
    /**
     * 系统默认错误处理
     *
     * @param int $errno 错误级别
     * @param string $errstr 错误信息
     * @param string $errfile 错误的文件
     * @param int $errline 错误的所在文件行号
     * @param array $errcontext 错误的上下文的符号表数组
     */
    static public function onError($errno, $errstr, $errfile, $errline, $errcontext){
        $info=array(
            'type'=>'error',
            'code'=> ($errno ? $errno : self::DEFAULT_ERROR_CODE),
            'message'=> $errstr,
            'file'=> str_replace(dirname(ROOT_PATH), '', $errfile),
            'line'=> $errline,
        );
        self::report($info, true);
    }
    
    /**
     * 系统默认异常处理
     *
     * @param \Exception $e 异常对象
     */
    static public function onException($e){
        $code=$e->getCode();
		$info=array(
            'type'=>'exception',
			'code'=> ($code ? $code : self::DEFAULT_ERROR_CODE),
			'message'=>$e->getMessage(),
			'line'=> $e->getLine(),
			'file'=> str_replace(dirname(ROOT_PATH), '', $e->getFile()),
		);
        self::report($info, true);
    }
    
    /**
     * 应用错误处理
     *
     * @param int $errno 错误级别
     * @param string $errstr 错误信息
     * @param string $errfile 错误的文件
     * @param int $errline 错误的所在文件行号
     * @param array $errcontext 错误的上下文的符号表数组
     */
    public function onAppError($errno, $errstr, $errfile, $errline, $errcontext){
        $info=array(
            'type'=>'error',
            'code'=> ($errno ? $errno : self::DEFAULT_ERROR_CODE),
            'message'=> $errstr,
            'file'=> str_replace(dirname(ROOT_PATH), '', $errfile),
            'line'=> $errline,
            'url'=> $this->context->getRequest()->server('REQUEST_URI'),
        );
        self::report($info);
        $this->export($info, $errcontext);
    }
    
    /**
     * 应用异常处理
     *
     * @param \Exception $e 异常对象
     */
    public function onAppException($e){
        $code=$e->getCode();
		$info=array(
            'type'=> 'exception',
			'code'=> ($code ? $code : self::DEFAULT_ERROR_CODE),
			'message'=> $e->getMessage(),
			'line'=> $e->getLine(),
			'file'=> str_replace(dirname(ROOT_PATH), '', $e->getFile()),
            'url'=> $this->context->getRequest()->server('REQUEST_URI'),
		);
        self::report($info);
        $this->export($info, $e);
    }

	/*
	 * 错误报告
	 * @param array $info 错误信息数组
     * @param bool $isPrint 是否打印错误
	 */
	static public function report(&$info, $isPrint=false){
        $info['file']=str_replace(dirname(KERNEL_PATH), '', $info['file']);
		if(C('errorlog')){
			$data='('.$info['code'].')'.$info['message'];
			$data.=' in '.$info['file'].'['.$info['line'].']';
			fastlog($data, true, $info['type'].'.log');
		}
        if($isPrint){
            $data='('.$info['code'].')'.$info['message']."\n";
			$data.='File: '.$info['file'].'['.$info['line']."]\n";
            echo $data;
        }
	}
	
	/*
	 * 错误输出
	 * @param Exception|Error $e 错误对象
	 * @param array $params 传递的参数
	 * @param bool $isReturn 是否返回渲染后的异常模板，否则直接输出
	 * @return string|void
	 */
	public function export(&$info, &$error){
        $response=$this->context->getResponse();
		if(env('PHP_SAPI','cli')=='cli'){ //命令行模式运行
			$data='('.$info['code'].')'.$info['message']."\n";
			$data.='File: '.$info['file'].'['.$info['line']."]\n";
            $response->end($data);
		}else if(env('IS_AJAX',false)){ //ajax模式运行
			$response->end($info);
		}else if($tpl=C('tmpl_exception_tpl')){  //web模式运行
			//直接访问模板
			$viewer=$this->context->make('\Library\View');
			$viewer->assign($info);
			$viewer->assign('e',$error);
            $data=$viewer->fetch($tpl);
            $response->end($data);
		}
	}
}

