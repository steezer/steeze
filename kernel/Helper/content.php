<?php

/**
 * 获取列表分页
 * @param array $info 分页信息数组
 * @param number $setPages 显示页数
 * @param string $urlRule 包含变量的URL规则模版参数
 * @param $array $array 字符变量数组
 * @return string 分页字符串
 * 分页信息范例：
 * 		array(
 * 			'type'=>'cpg',  //分页参数 
 * 			'total'=> $totalrows,  //记录总数 
 * 			'cPage'=> $currentpage,  //当前页数 
 * 			'size'=> $pagesize,  //每页大小 
 * 			'url'=> $curl, //分页url
 * 			'pFunc'=>'showPage(\'?\')' //js回调函数
 * 		)
 */
function content_list_pages($info,$setPages=10,$urlRule='',$array=array()){
	$defaults=array('type' => 'page','size' => 15,'url' => get_url(1));
	$addUrl='';
	$infos=array_merge($defaults, $info);
	$click_func=isset($info['pFunc']) ? $info['pFunc'] : '';
	if(isset($GLOBALS['URL_RULE']) && $urlRule == ''){
		$urlRule=$GLOBALS['URL_RULE'];
		$array=$GLOBALS['URL_ARRAY'];
	}elseif($urlRule == ''){
		$urlRule=url_par($infos['type'] . '={$page}', $infos['url'], $infos['type']);
	}
	unset($info);
	
	$multipage='';
	if($infos['total'] > $infos['size']){
		$page=$setPages + 1;
		$offset=ceil($setPages / 2 - 1);
		$pages=ceil($infos['total'] / $infos['size']);
		$from=$infos['cPage'] - $offset;
		$to=$infos['cPage'] + $offset;
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
		
		$multipage.=content_page_tag('span', array('class' => 'page_info'), '共' . $infos['total'] . '条信息', $click_func);
		if($infos['cPage'] > 0){
			if($infos['cPage'] == 1){
				$multipage.=content_page_tag('span', array('class' => 'page_pre page_none'), '上一页', $click_func);
			}else{
				$multipage.=content_page_tag('a', array('class' => 'page_pre','href' => content_page_url($infos['cPage'] - 1, $urlRule, $array)), '上一页', $click_func);
			}
			
			if($infos['cPage'] == 1){
				$multipage.=content_page_tag('span', array('class' => 'page_cur'), '1', $click_func);
			}elseif($infos['cPage'] > 6 && $more){
				$multipage.=content_page_tag('a', array('class' => 'page_no','href' => content_page_url(1, $urlRule, $array)), '1', $click_func) . content_page_tag('span', array('class' => 'page_dot'), '..', $click_func);
			}else{
				$multipage.=content_page_tag('a', array('class' => 'page_no','href' => content_page_url(1, $urlRule, $array)), '1', $click_func);
			}
		}
		
		for($i=$from; $i <= $to; $i++){
			if($i != $infos['cPage']){
				$multipage.=content_page_tag('a', array('class' => 'page_no','href' => content_page_url($i, $urlRule, $array)), $i, $click_func);
			}else{
				$multipage.=content_page_tag('span', array('class' => 'page_cur'), $i, $click_func);
			}
		}
		
		if($infos['cPage'] < $pages){
			if($infos['cPage'] < $pages - 5 && $more){
				$multipage.=content_page_tag('span', array('class' => 'page_dot'), '..', $click_func) . content_page_tag('a', array('class' => 'page_no','href' => content_page_url($pages, $urlRule, $array)), $pages, $click_func) . content_page_tag('a', array(
						'class' => 'page_next',
						'href' => content_page_url($infos['cPage'] + 1, $urlRule, $array)), '下一页', $click_func);
			}else{
				$multipage.=content_page_tag('a', array('class' => 'page_no','href' => content_page_url($pages, $urlRule, $array)), $pages, $click_func) . content_page_tag('a', array('class' => 'page_next','href' => content_page_url($infos['cPage'] + 1, $urlRule, $array)), '下一页', $click_func);
			}
		}elseif($infos['cPage'] == $pages){
			$multipage.=content_page_tag('span', array('class' => 'page_cur'), $pages, $click_func) . content_page_tag('span', array('class' => 'page_next page_none'), '下一页', $click_func);
		}else{
			$multipage.=content_page_tag('a', array('class' => 'page_no','href' => content_page_url($pages, $urlRule, $array)), $pages, $click_func) . content_page_tag('span', array('class' => 'page_next page_none'), '下一页', $click_func);
		}
	}
	return $multipage;
}

/**
 * 内容页分页
 *
 * @param $total 总页数
 * @param $currPage 当前页
 * @param $pageUrls 所有页面的url集合
 * @return string 分页字符串
 */
function content_detail_pages($total,$currPage,$pageUrls){
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
 * @param unknown $tagName 标签名称
 * @param array $arr 标签属性数组
 * @param string $text 标签文本
 * @param string $openFunc 标签点击事件函数
 * @return string 标签字符串
 */
function content_page_tag($tagName,$arr,$text='',$openFunc=''){
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
 * @param unknown $page 页数
 * @param unknown $urlRule 包含变量的URL规则模版参数
 * @param unknown $array 字符变量数组
 * @return string 生成的URL
 */
function content_page_url($page,$urlRule,$array){
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
function url_par($par,$url='',$key='page'){
	if($url == ''){
		$url=get_url(1);
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
function get_url($type=0){
	$server=make(Library\Request::class)->server();
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
