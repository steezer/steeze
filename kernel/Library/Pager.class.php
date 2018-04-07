<?php
namespace Library;

/**
 * 分页处理类
 */
class Pager{
	/**
	 * 分页配置
	 */
	private $config=[
		'previous'=>'<li><a href="[url]">上一页</a></li>',
		'next'=>'<li><a href="[url]">下一页</a></li>',
		'no'=>'<li><a href="[url]">[no]</a></li>',
		'current'=>'<li class="disabled"><span>[no]</span></li>',
		'dot'=>'<li class="disabled"><span>..</span></li>',
		'first'=>'<li><a href="[url]">[no]</a></li>',
		'last'=>'<li><a href="[url]">[no]</a></li>',
		'info'=>'显示[total]条记录中的[start]-[end]条',
		'none'=>'没有记录信息！',
		'only'=>'共[total]条记录',
	];

	/**
	 * 设置分页配置
	 * @param string|array $key 分页配置数组或配置单项
	 * @param string|null $value 配置值
	 */
	public function setConfig($key,$value=null){
		if(is_array($key)){
			$this->config=array_merge($this->config,$key);
		}else{
			$this->config[$key]=$value;
		}
	}
	
	/**
	 * 获取列表分页
	 * @param array $config 分页信息数组
	 * @param number $setPages 显示页数
	 * @param string $urlRule 包含变量的URL规则模板参数
	 * @param $array $array 字符变量数组
	 * @return string 分页字符串
	 * 分页信息参数范例：
	 * 		array(
	 * 			'type'=>'page',  //分页参数
	 * 			'total'=> $totalrows,  //记录总数
	 * 			'page'=> $currentpage,  //当前页数
	 * 			'size'=> $pagesize,  //每页大小
	 * 			'url'=> $curl, //分页url
	 * 			'callback'=>'showPage(\'?\')' //js回调函数
	 * 		)
	 */
	function getListPager($config,$setPages=10,$urlRule='',$array=array()){
		$defaults=array('type' => 'page','size' => 15,'url' => $this->getUrl(1));
		$addUrl='';
		$configs=array_merge($defaults, $config);
		$callback=isset($config['callback']) ? $config['callback'] : '';
		if(isset($GLOBALS['URL_RULE']) && $urlRule == ''){
			$urlRule=$GLOBALS['URL_RULE'];
			$array=$GLOBALS['URL_ARRAY'];
		}elseif($urlRule == ''){
			$urlRule=$this->urlParam($configs['type'] . '={$page}', $configs['url'], $configs['type']);
		}
		unset($config);
		
		
		$info=$configs['total']==0 ? $this->config['none'] : 
					str_replace('[total]', $configs['total'], $this->config['only']);
		$html='';
		if($configs['total'] > $configs['size']){
			$configs['page']=max(intval($configs['page']),1);
			$configs['start']=($configs['page']-1) * $configs['size']+1;
			$configs['end']=min($configs['page'] * $configs['size'],$configs['total']);
			
			$page=$setPages + 1;
			$offset=ceil($setPages / 2 - 1);
			$pages=ceil($configs['total'] / $configs['size']);
			$from=$configs['page'] - $offset;
			$to=$configs['page'] + $offset;
			$more=0;
			if($page >= $pages){
				$from=2;
				$to=$pages - 1;
			}else{
				if($from <= 1){
					$to=$page - 1;
					$from=2;
				}elseif($to >= $pages){
					$from=$pages - ($page - 2);
					$to=$pages - 1;
				}
				$more=1;
			}
			
			$result=[];
			
			$page_previous=$this->config['previous'];
			$page_next=$this->config['next'];
			$page_no=$this->config['no'];
			$page_current=$this->config['current'];
			$page_dot=$this->config['dot'];
			$page_first=$this->config['first'];
			$page_last=$this->config['last'];
			$page_info=$this->config['info'];
			
			foreach($configs as $k=>$v){
				$page_info=str_replace('['.$k.']', $v, $page_info);
			}
			$info=$page_info;
			
			if($configs['page'] > 0){
				if($configs['page'] == 1){
					$html.=str_replace('[no]', 1, $page_current);
				}else{
					$html.=str_replace('[url]', $this->pageUrl($configs['page'] - 1, $urlRule, $array), $page_previous);
					$html.=str_replace(['[url]','[no]'], [$this->pageUrl(1, $urlRule, $array),1], $page_first);
					
					if($configs['page'] > 6 && $more){
						$html.=$page_dot;
					}
				}
			}
			
			for($i=$from; $i <= $to; $i++){
				if($i != $configs['page']){
					$html.=str_replace(['[url]','[no]'], [$this->pageUrl($i, $urlRule, $array),$i], $page_no);
				}else{
					$html.=str_replace('[no]', $i, $page_current);
				}
			}
			
			if($configs['page'] < $pages){
				if($configs['page'] < $pages - 5 && $more){
					$html.=$page_dot;
				}
				$html.=str_replace(['[url]','[no]'], [$this->pageUrl($pages, $urlRule, $array),$pages], $page_last);
				$html.=str_replace(['[url]','[no]'], [$this->pageUrl($configs['page'] + 1, $urlRule, $array),$configs['page'] + 1], $page_next);
			}elseif($configs['page'] == $pages){
				$html.=str_replace('[no]', $pages, $page_current);
			}else{
				$html.=str_replace(['[url]','[no]'], [$this->pageUrl($pages, $urlRule, $array),$pages], $page_last);
			}
		}
		return ['html'=>$html,'info'=>$info];
	}
	
	/**
	 * 内容页分页
	 *
	 * @param int $total 总页数
	 * @param int $currPage 当前页
	 * @param string $$this->pageUrls 所有页面的url集合
	 * @return string 分页字符串
	 */
	function getDetailPager($total,$currPage,$pageUrls){
		$multipage='';
		$page=11;
		$offset=4;
		$pages=$total;
		$from=$currPage - $offset;
		$to=$currPage + $offset;
		$more=0;
		if($page >= $pages){
			$from=2;
			$to=$pages - 1;
		}else{
			if($from <= 1){
				$to=$page - 1;
				$from=2;
			}elseif($to >= $pages){
				$from=$pages - ($page - 2);
				$to=$pages - 1;
			}
			$more=1;
		}
		
		if($currPage > 0){
			if($currPage == 1){
				$multipage.='<span class="page_pre page_none">上一页</span>';
			}else{
				$multipage.='<a class="page_pre" href="' . $pageUrls[$currPage - 1] . '">上一页</a>';
			}
			if($currPage == 1){
				$multipage.=' <span class="page_cur">1</span>';
			}elseif($currPage > 6 && $more){
				$multipage.=' <a class="page_no" href="' . $pageUrls[1] . '">1</a><span class="page_dot">..</span>';
			}else{
				$multipage.=' <a class="page_no" href="' . $pageUrls[1] . '">1</a>';
			}
		}
		
		for($i=$from; $i <= $to; $i++){
			if($i != $currPage){
				$multipage.=' <a class="page_no" href="' . $pageUrls[$i] . '">' . $i . '</a>';
			}else{
				$multipage.=' <span class="page_cur" >' . $i . '</span>';
			}
		}
		
		if($currPage < $pages){
			if($currPage < $pages - 5 && $more){
				$multipage.=' <span class="page_dot">..</span><a class="page_no" href="' . $pageUrls[$pages] . '">' . $pages . '</a> <a class="page_next" href="' . $pageUrls[$currPage + 1] . '">下一页</a>';
			}else{
				$multipage.=' <a class="page_no" href="' . $pageUrls[$pages] . '">' . $pages . '</a> <a class="page_next" href="' . $pageUrls[$currPage + 1] . '">下一页</a>';
			}
		}elseif($currPage == $pages){
			$multipage.=' <span class="page_cur">' . $pages . '</span> <span class="page_next page_none">下一页</span>';
		}else{
			$multipage.=' <a class="page_no" href="' . $pageUrls[$currPage] . '">' . $pages . '</a> <span class="page_next page_none">下一页</span>';
		}
		return $multipage;
	}
	
	/**
	 * 生成分页html标签
	 *
	 * @param string $tagName 标签名称
	 * @param array $arr 标签属性数组
	 * @param string $text 标签文本
	 * @param string $openFunc 标签点击事件函数
	 * @return string 标签字符串
	 */
	private function pageTag($tagName,$arr,$text='',$openFunc=''){
		$attr=array();
		$attr[]='<' . $tagName;
		
		if(!empty($openFunc) && strtolower($tagName) == 'a' && isset($arr['href'])){
			if(isset($arr['onclick'])){
				$arr['onclick']+=';' + str_replace('?', $arr['href'], $openFunc);
			}else{
				$arr['onclick']=str_replace('?', $arr['href'], $openFunc);
			}
			$arr['href']='javascript:';
		}
		
		foreach($arr as $ky=>$vl){
			$attr[]=$ky . '="' . $vl . '"';
		}
		$attr[]=empty($text) ? '/>' : ('>' . $text . '</' . $tagName . '>');
		return implode(' ', $attr);
	}
	
	/**
	 * 生成分页URL
	 *
	 * @param int $page 页数
	 * @param string $urlRule 包含变量的URL规则模板参数
	 * @param array $array 字符变量数组
	 * @return string 生成的URL
	 */
	private function pageUrl($page,$urlRule,$array){
		if(strpos($urlRule, '~')){
			$urlRules=explode('~', $urlRule);
			$urlRule=$page < 2 ? $urlRules[0] : $urlRules[1];
		}
		$findme=array('{$page}');
		$replaceme=array($page);
		if(is_array($array)){
			foreach($array as $k=>$v){
				$findme[]='{$' . $k . '}';
				$replaceme[]=$v;
			}
		}
		$url=str_replace($findme, $replaceme, $urlRule);
		$url=str_replace(array('http://','//','~'), array('~','/','http://'), $url);
		return $url;
	}
	
	/**
	 * 根据原始URL及新增参数重新URL
	 *
	 * @param array|string $par 新增的参数
	 * @param string $url 原始URL
	 * @param string $key 需要排除的URL参数
	 * @return string 重新设置的URL
	 */
	private function urlParam($par,$url='',$key='page'){
		if($url == ''){
			$url=getUrl(1);
		}
		$pos=strpos($url, '?');
		if($pos === false){
			$url.='?' . (is_array($par) ? http_build_query($par) : $par);
		}else{
			$querystring=substr(strstr($url, '?'), 1);
			$pars=explode('&', $querystring);
			$querystring='';
			foreach($pars as $kv){
				if(strpos($kv, '=') === false){
					$k=$kv;
					$v=false;
				}else{
					$k=substr($kv, 0, strpos($kv, '='));
					$v=substr($kv, strpos($kv, '=') + 1);
				}
				if($k != $key){
					$querystring.=$k . ($v === false ? '' : '=' . $v) . '&';
				}
			}
			
			$querystring=($querystring ? $querystring : '') . (is_array($par) ? http_build_query($par) : $par);
			$url=substr($url, 0, $pos) . (empty($querystring) ? '' : '?' . $querystring);
		}
		return $url;
	}
	
	/**
	 * 获取当前页面URL地址
	 *
	 * @param int $type 需要获取的类型，取值及返回值意义 0->绝对地址，1->相对地址，2->不带参数绝对地址，3->不带参数相对地址
	 * @return string 获取的URL，类型由$type决定
	 */
	private function getUrl($type=0){
		$server=make(Request::class)->server();
		$sys_protocal=env('SITE_PROTOCOL');
		$php_self=$server['php_self'] ? $server['php_self'] : $server['script_name'];
		$path_info=isset($server['path_info']) ? $server['path_info'] : '';
		$relate_url=isset($server['request_uri']) ? $server['request_uri'] : $php_self . (isset($server['query_string']) ? '?' . safe_replace($server['query_string']) : $path_info);
		if(strpos($relate_url, '?')===false && !empty($server['query_string'])){
			$relate_url.='?'.$server['query_string'];
		}
		$relate_url_nopara=strpos($relate_url, '?') === false ? $relate_url : substr($relate_url, 0, strpos($relate_url, '?'));
		switch($type){
			case 0:
				return $sys_protocal . env('SITE_HOST') . $relate_url;
				break;
			case 1:
				return $relate_url;
				break;
			case 2:
				return $sys_protocal . env('SITE_HOST') . $relate_url_nopara;
				break;
			case 3:
				return $relate_url_nopara;
				break;
		}
	}
}