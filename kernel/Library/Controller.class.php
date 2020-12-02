<?php
namespace Library;

/**
 * 控制器基类
 * 
 * @package Library
 */
class Controller{
    
    /**
     * 定义中间件
     */
    const MIDDLEWARE=null;
    
    /**
     * 视图对象
     *
     * @var View
     */
	private $view=null;
    
    /**
     * 应用上下文对象
     *
     * @var Application
     */
    private $context=null;
    
    /**
     * 设置中间件
     *
     * @return array
     * 此函数由系统框架调用
     */
	public static function middleware(){
		return array();
	}
	
    /**
     * 设置应用上下文对象（系统自动注入）
     *
     * @param Application $context
     * @return Controller
     */
    public function setContext(Application $context){
        $this->context=$context;
        return $this;
    }
    
    /**
     * 获取应用上下文对象
     *
     * @return Application
     */
    protected function getContext(){
        return $this->context;
    }
    
    /**
	 * 获取视图对象
	 *
	 * @return View
	 */
	protected function getView(){
		if(is_null($this->view)){
            $this->view=make('\Library\View', $this->context);
		}
		return $this->view;
	}
    
    /**
	 * 获取列表分页
     * 
	 * @param array $config 分页信息参数配置
	 * @param int $setPages 显示页数（可选），默认：10
	 * @param string $urlRule 包含变量的URL规则模板（可选），默认：{type}={page}
	 * @param array $array 附加的参数（可选）
	 * @return array 分页配置，包括html和info字段
	 * 
     * 分页信息参数范例：
	 * 		[
     *          'total'=> $totalrows,  //记录总数
     *          'page'=> $currentpage,  //当前分页，支持例如：“3, 5”（当前第3页，分页大小为5）
     *          'size'=> $pagesize,  //每页大小（可选），默认：15
     *          'url'=> $curl, //分页URL（可选），默认使用当前页
	 * 			'type'=>'page',  //分页参数（可选），默认:page
	 * 			'callback'=>'showPage(\'?\')', //js回调函数（可选）
	 * 		]
	 */
    protected function getPager($config=array(), $setPages=10, $urlRule='', $array=array()){
        $this->getView()->getPager($config, $setPages, $urlRule, $array);
    }
    
    /**
     * 渲染输出控制器方法的返回值
     *
     * @param Controller $controller
     * @param string $action
     * @param array $param
     * @return mixed
     */
	protected function render($controller, $action, array $param=array()){
		return $this->getView()->render($controller, $action, $param);
	}

	/**
	 * 模板显示 调用内置的模板引擎显示方法，
	 *
	 * @param string|array $file 指定要调用的模板文件 默认为空则由系统自动定位模板文件，为数组则传递模版变量
     * @param array|int $data 如果是否数组则为模板变量，如果为整数则赋值给$option参数
	 * @param int $option 返回数据的后续处理选项
     * @return bool 如果模板文件不存在，返回false， 否则返回true
     * 
     * $option参数值说明：
     *   0: 未结束请求，服务端可以继续输出；
     *   1: 结束请求，服务端停止输出，后续代码中运行；
     *   2: 结束请求，服务端停止输出，后续代码继续运行（异步请求）；
	 */
	protected function display($file='', $data=null, $option=1){
        if(is_array($file)){
            $data=$file;
            $file='';
        }
        if(is_array($data)){
            $this->getView()->assign($data);
        }elseif(is_int($data)){
            $option=$data;
            $data=array();
        }
        
        $ajax=env('IS_AJAX');
        $type=!$ajax ? 'html' : '';
        $result=null;
        if(!$ajax || $ajax!=1){
            $result=$this->getView()->fetch($file);
            if(is_null($result)){
                return false;
            }
        }else{
            $data=$this->getView()->get();
        }
        
        if(!$ajax){
            // 非ajax请求
            $this->ajaxReturn($result, $type, $option);
        }else{
            $data=array(
                    'code'=>0,
                    'message'=>L('success'),
                    'data'=>(array)$data
                );
            if($ajax!=1){
                // 直接请求视图
                $data['view']=$result;
            }
            $this->ajaxReturn($data, $type, $option);
        }
        
        return true;
	}

	/**
	 * 输出内容文本可以包括Html 并支持内容解析
	 *
	 * @param string $content 输出内容
     * @param array $data 如果是否数组则为模板变量，如果为整数则赋值给$option参数
     * @param int $option 返回数据的后续处理选项
     * 
     * $option参数值说明：
     *   0: 未结束请求，服务端可以继续输出；
     *   1: 结束请求，服务端停止输出，后续代码中运行；
     *   2: 结束请求，服务端停止输出，后续代码继续运行（异步请求）；
	 */
	protected function show($content='', $data=null, $option=1){
        if(is_array($data)){
            $this->getView()->assign($data);
        }elseif(is_int($data)){
            $option=$data;
            $data=array();
        }
        $ajax=env('IS_AJAX');
        $type=!$ajax ? 'html' : '';
        $result=null;
        if(!$ajax || $ajax!=1){
            $result=$this->getView()->fetch('', $content);
            if(is_null($result)){
                return false;
            }
        }else{
            $data=$this->getView()->get();
        }
        
        if(!$ajax){
            // 非ajax请求
            $this->ajaxReturn($result, $type, $option);
        }else{
            $data=array(
                    'code'=>0,
                    'message'=>L('success'),
                    'data'=>(array)$data
                );
            if($ajax!=1){
                // 直接请求视图
                $data['view']=$result;
            }
            $this->ajaxReturn($data, $type, $option);
        }
	}

	/**
	 * 获取输出页面内容 调用内置的模板引擎fetch方法，
	 *
	 * @param string $file 指定要调用的模板文件，默认为空 由系统自动定位模板文件
	 * @param string|array $data 渲染输出的内容，如果为空字符串则使用文件渲染，如果为数组则为模板变量
	 * @return string
	 */
	protected function fetch($file='', $data=''){
		return $this->getView()->fetch($file, $data);
	}
    
    /**
     * 从字符串模版渲染
     *
     * @param string $str 模版字符串
     * @param array $data 参数数据
     * @return string
     */
    protected function fetchString($str, $data=null){
        return $this->getView()->fetchString($str, $data);
    }
    
    /**
     * 从模版文件渲染
     *
     * @param string $file 模版文件
     * @param array $data 参数数据
     * @return string
     */
    protected function fetchFile($file, $data=null){
        return $this->getView()->fetchFile($file, $data);
    }

	/**
	 * 创建静态页面
	 *
	 * @param string htmlfile 生成的静态文件名称 
	 * @param string htmlpath 生成的静态文件路径，默认生成到系统根目录
	 * @param string $templateFile 指定要调用的模板文件 默认为空 由系统自动定位模板文件
	 * @return string
	 */
	protected function buildHtml($htmlfile, $htmlpath='', $templateFile=''){
		$content=$this->fetch($templateFile);
		$htmlpath=!empty($htmlpath) ? $htmlpath : ROOT_PATH;
		$htmlfile=$htmlpath . $htmlfile . C('HTML_FILE_SUFFIX', '.html');
		$fdir=dirname($htmlfile);
		!is_dir($fdir) && mkdir($fdir, 0777, true);
		file_put_contents($htmlfile, $content);
		return $content;
	}

	/**
	 * 模板变量赋值
	 *
	 * @param mixed $name 要显示的模板变量
	 * @param mixed $value 变量的值
	 * @return Controller
	 */
	protected function assign($name, $value=''){
		$this->getView()->assign($name, $value);
		return $this;
	}

	/**
	 * 取得模板显示变量的值
	 *
	 * @access protected
	 * @param string $name 模板显示变量
	 * @return mixed
	 */
	protected function get($name=''){
		return $this->getView()->get($name);
	}

	/**
	 * 操作错误跳转的快捷方法
	 *
	 * @param null|string $message 错误信息
	 * @param int|string|bool $code 错误码，默认为1
	 * @param string|bool|int $jumpUrl 页面跳转地址
	 * @param bool|int $ajax 是否为Ajax方式 当数字时指定跳转时间
     * 
	 * 调用方式：
	 * 1. error($message,$code,$jumpUrl,$ajax)
	 * 2. error($message,$code,$jumpUrl)
	 * 3. error($message,$code)
	 * 4. error($message)
     * 5. error()
	 */
	protected function error($message=null, $code=1, $jumpUrl='', $ajax=false){
        if(is_bool($jumpUrl) || is_int($jumpUrl)){
            $ajax=$jumpUrl;
            $jumpUrl='';
        }
		if(is_string($code)){
			$jumpUrl=$code;
			$code=1;
		}elseif(is_bool($code)){
			$ajax=$code;
			$code=1;
		}
        if(is_null($message)){
            $message=L('error');
        }
		$this->messageReturn($message, $code, $jumpUrl, $ajax);
	}

	/**
	 * 操作成功跳转的快捷方法
	 *
	 * @param null|string|array $message 提示信息，如果为数组则设置到返回data字段里面
	 * @param string|array $jumpUrl 页面跳转地址，如果为数组则设置到返回data字段里面
	 * @param int|bool $ajax 是否为Ajax方式 当数字时指定跳转时间
     * 
     * 调用方式：
	 * 1. success($message,$jumpUrl,$ajax)
	 * 2. success($message,$jumpUrl)
	 * 3. success($message)
     * 5. success()
	 */
	protected function success($message=null,$jumpUrl='',$ajax=false){
        if(is_bool($jumpUrl) || is_int($jumpUrl)){
            $ajax=$jumpUrl;
            $jumpUrl='';
        }
        if(is_null($message)){
            $message=L('success');
        }else if(is_array($message)){
            $jumpUrl=$message;
            $message=L('success');
        }
		$this->messageReturn($message, 0, $jumpUrl, $ajax);
	}

	/**
	 * Ajax方式返回数据到客户端
	 *
	 * @param mixed $data 要返回的数据，默认返回模板变量
	 * @param string|int $type 为字符串AJAX返回数据格式（默认：JSON），为整数则赋值给$option参数
	 * @param int $option 返回数据的后续处理选项
     * 
     * $option参数值说明：
     *   0: 未结束请求，服务端可以继续输出；
     *   1: 结束请求，服务端停止输出，后续代码中运行；
     *   2: 结束请求，服务端停止输出，后续代码继续运行（异步请求）；
	 */
	protected function ajaxReturn($data=null, $type='', $option=1){
		if(is_int($type)){
            $option=$type;
            $type='';
        }
        if($type===''){
			$type=C('DEFAULT_AJAX_RETURN', 'JSON');
		}
		if(is_null($data)){
			$data=$this->getView()->get(); //使用模板变量
		}
        $type=strtolower($type);
        if($type=='jsonp'){
            $varHdl=C('VAR_JSONP_HANDLER', 'callback');
            $request=$this->getContext()->getRequest();
            $handler=$request->get($varHdl,C('DEFAULT_JSONP_HANDLER', 'jsonpReturn'));
            $data=$handler . '(' . to_string($data) . ');';
            $type='js';
        }
        $response=$this->getContext()->getResponse();
        $response->write($data, C('mimetype.'.$type), 'utf-8');
        $option && $response->end(null, $option==2);
	}
    
    /**
	 * 跳转操作 支持错误导向和正确跳转 调用模板显示 
	 *
	 * @param string $message 提示信息
	 * @param int $code 状态码
	 * @param string $jumpUrl 页面跳转地址
	 * @param bool|int $ajax 是否为Ajax方式 当数字时指定跳转时间
     * 
     * 默认为public目录下面的success页面 提示页面为可配置 支持模板标签
	 */
	private function messageReturn($message, $code=0, $jumpUrl='', $ajax=false){
        $response=$this->getContext()->getResponse();
		if(true === $ajax || env('IS_AJAX')){ // AJAX提交
			$data=is_array($ajax) ? $ajax : array();
			$data['message']=$message;
			$data['code']=$code;
			if(is_array($jumpUrl)){
				$data['data']=$jumpUrl;
				$jumpUrl='';
            }
            if(!empty($jumpUrl)){
                $data['url']=$jumpUrl;
            }
            $response->flush($data, C('mimetype.json'), 'utf-8');
		}else{
			is_int($ajax) && $this->assign('waitSecond', $ajax*1000);
			if(is_array($jumpUrl)){
				$this->assign('data', $jumpUrl);
				$jumpUrl='';
			}
			!empty($jumpUrl) && $this->assign('jumpUrl', $jumpUrl);
			$this->assign('msgTitle', !$code ? L('success') : L('error'));
			$this->get('closeWin') && $this->assign('jumpUrl', 'close');
			$this->assign('code', $code); // 状态
			$this->assign('message', $message); // 提示信息
			$waitSecond=$this->get('waitSecond');
			$jumpUrl=$this->get('jumpUrl');
			!isset($jumpUrl) && $this->assign('jumpUrl', 'auto');
			if(!$code){
				!isset($waitSecond) && $this->assign('waitSecond', 1000);
				$this->display(C('TMPL_ACTION_SUCCESS', '/message'));
			}else{
				!isset($waitSecond) && $this->assign('waitSecond', 3000);
				$this->assign('error', $message);
				$this->display(C('TMPL_ACTION_ERROR', '/message'));
			}
		}
        
        //结束所有输出
        $response->end();
	}
	
}