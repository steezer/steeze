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
    public function getContext(){
        return $this->context;
    }
    
    /**
     * 渲染输出控制器方法的返回值
     *
     * @param Controller $controller
     * @param string $action
     * @param array $param
     * @return mixed
     */
	public function render($controller, $action, array $param=array()){
		return $this->view()->render($controller, $action, $param);
	}

	/**
	 * 模板显示 调用内置的模板引擎显示方法，
	 *
	 * @param string $file 指定要调用的模板文件 默认为空则由系统自动定位模板文件
     * @param array $data 模板变量组成的数组
	 */
	public function display($file='', $data=array()){
        if(is_array($data)){
            $this->view()->assign($data);
        }
        env('IS_AJAX') ? $this->success($this->view()->get()) : 
                $this->view()->display($file);
	}

	/**
	 * 输出内容文本可以包括Html 并支持内容解析
	 *
	 * @param string $content 输出内容
     * @param array $data 模板变量组成的数组
	 */
	public function show($content='', $data=array()){
        if(is_array($data)){
            $this->view()->assign($data);
        }
		$this->view()->display('', $content);
	}

	/**
	 * 获取输出页面内容 调用内置的模板引擎fetch方法，
	 *
	 * @param string $file 指定要调用的模板文件，默认为空 由系统自动定位模板文件
	 * @param string|array $data 渲染输出的内容，如果为空字符串则使用文件渲染，如果为数组则为模板变量
	 * @return string
	 */
	public function fetch($file='', $data=''){
		return $this->view()->fetch($file, $data);
	}

	/**
	 * 创建静态页面
	 *
	 * @param string htmlfile 生成的静态文件名称 
	 * @param string htmlpath 生成的静态文件路径，默认生成到系统根目录
	 * @param string $templateFile 指定要调用的模板文件 默认为空 由系统自动定位模板文件
	 * @return string
	 */
	public function buildHtml($htmlfile, $htmlpath='', $templateFile=''){
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
	public function assign($name, $value=''){
		$this->view()->assign($name, $value);
		return $this;
	}

	/**
	 * 取得模板显示变量的值
	 *
	 * @access protected
	 * @param string $name 模板显示变量
	 * @return mixed
	 */
	public function get($name=''){
		return $this->view()->get($name);
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
	public function error($message=null, $code=1, $jumpUrl='', $ajax=false){
		if(is_string($code)){
			if(is_bool($jumpUrl) || is_int($jumpUrl)){
				$ajax=$jumpUrl;
			}
			$jumpUrl=$code;
			$code=1;
		}elseif(is_bool($code)){
			$ajax=$code;
			$code=1;
		}
        if(is_null($message)){
            $message=L('error');
        }
		$this->dispatchJump($message, $code, $jumpUrl, $ajax);
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
	public function success($message=null,$jumpUrl='',$ajax=false){
        if(is_null($message)){
            $message=L('success');
        }else if(is_array($message)){
            $jumpUrl=$message;
            $message=L('success');
        }
		$this->dispatchJump($message, 0, $jumpUrl, $ajax);
	}

	/**
	 * Ajax方式返回数据到客户端
	 *
	 * @param mixed $data 要返回的数据，默认返回模板变量
	 * @param String $type AJAX返回数据格式，默认返回JSON格式
	 * @param int $option 传递给json_encode的option参数
	 */
	public function ajaxReturn($data=null, $type='', $option=null){
		if(empty($type)){
			$type=C('DEFAULT_AJAX_RETURN', 'JSON');
		}
		if(is_null($data)){
			$data=$this->view()->get(); //使用模板变量
		}
        $type=strtolower($type);
		switch($type){
			case 'json':
				// 返回JSON数据格式到客户端 包含状态信息
                $data=to_string($data, $option);
				break;
			case 'jsonp':
				// 返回JSON数据格式到客户端 包含状态信息
				$varHdl=C('VAR_JSONP_HANDLER', 'callback');
				$request=$this->getContext()->getRequest();
				$handler=$request->get($varHdl,C('DEFAULT_JSONP_HANDLER', 'jsonpReturn'));
                $data=$handler . '(' . to_string($data, $option) . ');';
                $type='js';
				break;
			case 'eval':
            case 'js':
                $type='js';
				break;
			default:
                $type='html';
                $data=var_export($data, true);
				break;
		}
        $response=$this->getContext()->getResponse();
        $response->flush($data, C('mimetype.'.$type), 'utf-8');
	}

	/**
	 * Action跳转(URL重定向） 支持指定模块和延时跳转
	 *
	 * @param string $url 跳转的URL表达式
	 * @param array $params 其它URL参数
	 * @param integer $delay 延时跳转的时间 单位为秒
	 * @param string $msg 跳转提示信息
	 */
	public function redirect($url, $params=array(), $delay=0, $msg=''){
		$targetUrl=U($url, $params);
		$this->context->getResponse()->redirect($targetUrl, $delay, $msg);
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
    public function getPager($config=array(), $setPages=10, $urlRule='', $array=array()){
        $this->view()->getPager($config, $setPages, $urlRule, $array);
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
	private function dispatchJump($message, $code=0, $jumpUrl='', $ajax=false){
        $response=$this->getContext()->getResponse();
		if(true === $ajax || env('IS_AJAX')){ // AJAX提交
			$data=is_array($ajax) ? $ajax : array();
			$data['message']=$message;
			$data['code']=$code;
			if(is_array($jumpUrl)){
				$data['data']=$jumpUrl;
				$jumpUrl='';
			}
			$data['url']=$jumpUrl;
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
    
	/**
	 * 获取视图对象
	 *
	 * @return View
	 */
	private function view(){
		if(is_null($this->view)){
            $this->view=make('\Library\View', $this->context);
		}
		return $this->view;
	}
	
}