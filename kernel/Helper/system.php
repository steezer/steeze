<?php
/**
 * 系统默认函数库
 * 
 * @package default
 * @subpackage Helper
 */

/**
 * 快速日志记录（支持日志分割及文件增量清理）
 *
 * @param mixed $content 日志内容
 * @param string|int|bool $tag 如果为字符串，则是日志标签；否则为是否附加日志
 * @param string $file 日志文件名
 * @return int 写入日志字节数
 */
function fastlog($content, $tag=true, $file='system.log'){
    $isAppend=!is_string($tag) ? (bool)$tag : true;
    $title=is_string($tag) ? $tag.' ' : '';
    $datetime=date('Y-m-d H:i:s');
    $content='[' . $datetime . '] '.$title.dump($content, true);
    $filename=LOGS_PATH . $file;
    $dirname=dirname($filename);
    !is_dir($dirname) && mkdir($dirname,0755,true);
    
    //处理日志分割及删除
    if(C('split_logfile', true)){
        $maxSize=max(C('max_logfile_size', 20), 0.01) * 1048576; //最小值为：10kb
        $maxNum=min(C('max_logfile_num', 5), 999); //最多999个日志
        
        $files=glob($filename.'*');
        $total=count($files);
        if($total>0){
            
            //自动清理先前的日志文件
            if($maxNum>0 && $total > $maxNum){
                foreach ($files as $k=> &$value) {
                    if($k>= $total-$maxNum){
                        break;
                    }
                    unlink($value);
                }
            }
            
            //判断是否生成新文件
            $lastFile=array_pop($files);
            if(filesize($lastFile) >= $maxSize){
                $num=intval(substr($lastFile, strlen($filename)))+1;
                $filename=$filename.str_pad($num, 3, '0', STR_PAD_LEFT);
            }else{
                $filename=$lastFile;
            }
        }
    }
    
    //日志写入
    return file_put_contents($filename, $content . "\n", ($isAppend ? FILE_APPEND : 0));
}

/**
 * 添加和获取页面Trace记录
 * @param string $value 变量
 * @param string $label 标签
 * @param string $level 日志级别(或者页面Trace的选项卡)
 * @param boolean $record 是否记录日志
 * @return void|array
 */
function trace($value=null, $label='', $level='DEBUG', $record=false) {
    static $_trace=[];
    if(is_null($value)){ // 获取trace信息
        return $_trace; 
    }
    $info=($label ? $label.':' : '') . print_r($value, true);
    $level=strtoupper($level);
    if( env('IS_AJAX', false) || !C('show_system_trace', false)  || $record) {
        fastlog('['.$level.']'.$info, true, 'trace.log');
    }else{
        if(!isset($_trace[$level]) || count($_trace[$level]) > C('trace_max_record', 100)) {
            $_trace[$level] =  [];
        }
        $_trace[$level][] = $info;
    }
}

/**
 * 输出变量信息
 * 
 * @param mixed $var 变量
 * @param bool $isReturn 是否返回值
 * @return void|mixed
 *
 */
function dump($var, $isReturn=false){
    if(is_array($var)){
        foreach($var as $k=>$v){
            if(is_object($v)){
                $var[$k]=dump($v, $isReturn);
            }
        }
    }
    
    $return=is_string($var) ? $var : 
                (
                    is_object($var) ? get_class($var).'[object]' : 
                    var_export($var, true)
                );
    if(!$isReturn){
        echo $return . "\n";
    }else{
        return $return;
    }
}

/**
 * 获取真实IP地址
 *
 * @param int $isOnline 是否在线获取本地IP
 * @return string
 */
function getip($isOnline=0){
	return Library\Client::getIpAddr($isOnline);
}

/**
 * 转换字节数为其他单位
 *
 * @param int $size
 * @param number $bits
 * @return string
 */
function sizeformat($size, $bits=2){
    if(!$size){
        return '0B';
    }
	$unit=array('B','KB','MB','GB','TB','PB');
	return round($size / pow(1024, ($i=floor(log($size, 1024)))), $bits) . $unit[$i];
}

/**
 * 转换字节数为其他单位
 *
 * @param int $tm 时间戳
 * @return string
 */
function timeformat($tm){
	$unit=array('秒','分钟','小时','天');
	if($tm < 60 && $tm > 0){
		return $tm . $unit[0];
	}else if($tm >= 60 && $tm < 3600){
		return floor($tm / 60) . $unit[1] . timeformat($tm % 60);
	}else if($tm >= 3600 && $tm < (3600 * 24)){
		return floor($tm / 3600) . $unit[2] . timeformat($tm % 3600);
	}else if($tm > 0){
		return floor($tm / (3600 * 24)) . $unit[3] . timeformat($tm % (3600 * 24));
	}
}

/**
 * 从指定目录根据知道路径读取文件列表（只检索当前目录）
 *
 * @param string $dir 需要获取的目录
 * @param string $type 过滤文件类型，多种类型用"|"隔开，如：jpg|png
 * @return array
 */
function filelist($dir='.', $type='*'){
    $farr=array();
    $types=$type!='*' ? explode('|', $type) : array();
    if($handle=opendir($dir)){
        while(false !== ($file=readdir($handle))){
            if($file != '.' && $file != '..'){
                if($type != '*'){
                    if(
                        ($pos=strrpos($file, '.')) && 
                        in_array(substr($file, $pos+1), $types)
                    ){
                        $farr[]=$file;
                    }
                }else{
                    $farr[]=$file;
                }
            }
        }
        closedir($handle);
    }
    return $farr;
}

/**
 * 生成缩略图
 *
 * @param string $imgUrl 需要处理的字符串
 * @param number $maxWidth 限制宽度，默认为0（不限制）
 * @param number $maxHeight 限制高度，默认为0（不限制）
 * @param number $cutType 剪裁类型，为0不剪裁，为1缩小剪裁，为2放大剪裁
 * @param string $defaultImg 图片不存在默认图片
 * @param number $isGetRemot 是否获取远程图片
 * @return string 处理后的图片路径（相对于网站根目录）
 */
function thumb($imgUrl, $maxWidth=0, $maxHeight=0, $cutType=0, $defaultImg='', $isGetRemot=0){
	return Library\Image::thumb($imgUrl, $maxWidth, $maxHeight, $cutType, $defaultImg, $isGetRemot);
}

/**
 * 简化路径
 * 
 * @param string $path 路径名称
 * @return string
 */
function simplify_ds($path){
	if(DS != '/'){
		$path=str_replace('/', DS, $path);
	}
	while(strpos($path, DS . DS) !== false){
		$path=str_replace(DS . DS, DS, $path);
	}
	return $path;
}

/**
 * 主要用于截取从0开始的任意长度的字符串(完整无乱码)
 *
 * @param string $sourcestr 待截取的字符串
 * @param number $cutlength 截取长度
 * @param bool $addfoot 是否添加"..."在末尾
 * @param bool &$isAdd 是否进行过截取操作
 * @return string
 */
function cut_str($sourcestr, $cutlength=80, $addfoot=true, &$isAdd=false){
	$isAdd=false;
	if(function_exists('mb_substr')){
		return mb_substr($sourcestr, 0, $cutlength, 'utf-8') . ($addfoot && ($isAdd=strlen_utf8($sourcestr) > $cutlength) ? '...' : '');
	}elseif(function_exists('iconv_substr')){
		return iconv_substr($sourcestr, 0, $cutlength, 'utf-8') . ($addfoot && ($isAdd=strlen_utf8($sourcestr) > $cutlength) ? '...' : '');
	}
	$returnstr='';
	$i=0;
	$n=0.0;
	$strLen=strlen($sourcestr); // 字符串的字节数
	while(($n < $cutlength) and ($i < $strLen)){
		$temp_str=substr($sourcestr, $i, 1);
		$ascnum=ord($temp_str); // 得到字符串中第$i位字符的ASCII码
		if($ascnum >= 252){ // 如果ASCII位高与252
			$returnstr=$returnstr . substr($sourcestr, $i, 6); // 根据UTF-8编码规范，将6个连续的字符计为单个字符
			$i=$i + 6; // 实际Byte计为6
			$n++; // 字串长度计1
		}elseif($ascnum >= 248){ // 如果ASCII位高与248
			$returnstr=$returnstr . substr($sourcestr, $i, 5); // 根据UTF-8编码规范，将5个连续的字符计为单个字符
			$i=$i + 5; // 实际Byte计为5
			$n++; // 字串长度计1
		}elseif($ascnum >= 240){ // 如果ASCII位高与240
			$returnstr=$returnstr . substr($sourcestr, $i, 4); // 根据UTF-8编码规范，将4个连续的字符计为单个字符
			$i=$i + 4; // 实际Byte计为4
			$n++; // 字串长度计1
		}elseif($ascnum >= 224){ // 如果ASCII位高与224
			$returnstr=$returnstr . substr($sourcestr, $i, 3); // 根据UTF-8编码规范，将3个连续的字符计为单个字符
			$i=$i + 3; // 实际Byte计为3
			$n++; // 字串长度计1
		}elseif($ascnum >= 192){ // 如果ASCII位高与192
			$returnstr=$returnstr . substr($sourcestr, $i, 2); // 根据UTF-8编码规范，将2个连续的字符计为单个字符
			$i=$i + 2; // 实际Byte计为2
			$n++; // 字串长度计1
		}elseif($ascnum >= 65 and $ascnum <= 90 and $ascnum != 73){ // 如果是大写字母 I除外
			$returnstr=$returnstr . substr($sourcestr, $i, 1);
			$i=$i + 1; // 实际的Byte数仍计1个
			$n++; // 但考虑整体美观，大写字母计成一个高位字符
		}elseif(!(array_search($ascnum, array(37,38,64,109,119)) === FALSE)){ // %,&,@,m,w 字符按１个字符宽
			$returnstr=$returnstr . substr($sourcestr, $i, 1);
			$i=$i + 1; // 实际的Byte数仍计1个
			$n++; // 但考虑整体美观，这些字条计成一个高位字符
		}else{ // 其他情况下，包括小写字母和半角标点符号
			$returnstr=$returnstr . substr($sourcestr, $i, 1);
			$i=$i + 1; // 实际的Byte数计1个
			$n=$n + 0.5; // 其余的小写字母和半角标点等与半个高位字符宽...
		}
	}
	
	if(($isAdd=$i < $strLen) && $addfoot){
		$returnstr=$returnstr . '...';
	} // 超过长度时在尾处加上省略号
	return $returnstr;
}

/**
 * 统计utf-8字符长度
 *
 * @param string $str 原始html字符串
 * @return string
 */
function strlen_utf8($str){
	if(function_exists('mb_strlen')){
		return mb_strlen($str, 'utf-8');
	}
	$i=0;
	$count=0;
	$len=strlen($str);
	while($i < $len){
		$chr=ord($str[$i]);
		$count++;
		$i++;
		if($i >= $len)
			break;
		if($chr & 0x80){
			$chr<<=1;
			while($chr & 0x80){
				$i++;
				$chr<<=1;
			}
		}
	}
	return $count;
}

/**
 * **********************URL处理系列函数*************************
 */

/**
 * 使用特定function对数组中所有元素做处理
 *
 * @param array $array 需要处理的数组
 * @param string $func 对数组处理的函数
 * @param bool $applyKey 是否同时处理数组键名
 */
function array_map_deep($array, $func, $applyKey=false){
	foreach($array as $key=>$value){
		$array[$key]=is_array($value) ? array_map_deep($array[$key], $func, $applyKey) : $func($value);
		if($applyKey){
			$new_key=$func($key);
			if($new_key != $key){
				$array[$new_key]=$array[$key];
				unset($array[$key]);
			}
		}
	}
	return $array;
}

/**
 * 改进后base64加密或解密
 *
 * @param array|string $data 数据
 * @param string $type 处理类型：ENCODE为加密，DECODE为解密，默认为ENCODE
 * @param array|string $filter 第一个参数为数组时，过滤的键名，默认为NULL
 * @param string $strip 是否对部分字符进行处理，处理后符合URL编码规范，默认为0（不处理）
 * @return string 处理后的数据
 */
function base64($data, $type='ENCODE', $filter=null, $strip=0){
	$type=strtoupper($type);
	$filterArr=is_array($filter) ? $filter : (is_string($filter) ? explode(',', $filter) : NULL);
	$strip=is_int($filter) ? $filter : $strip;
	if(is_array($data)){
		foreach($data as $ky=>$vl){
			if(empty($filterArr) || in_array($ky, $filterArr)){
				$data[$ky]=base64($vl, $type, $filter, $strip);
			}
		}
	}else{
		$searchs=array('=','/','+');
		$replaces=array('_','-','$');
		if($type != 'DECODE'){
			$data=base64_encode($data);
			if($strip){
				$data=str_replace($searchs, $replaces, $data);
			}
		}else{
			if($strip){
				$data=str_replace($replaces, $searchs, $data);
			}
			$data=base64_decode($data);
		}
	}
	return $data;
}

/**
 * 系统动态加密解密可以设置过期时间的字符串（通常用于授权）
 *
 * @param string $str 需要处理的字符串
 * @param string $operation 处理类型：ENCODE为加密，DECODE为解密，默认为ENCODE
 * @param string $key 自定义秘钥，默认为空
 * @param string $expiry 过期时间，默认为0，不限制
 * @return string 处理后的数据
 */
function sys_auth($str, $operation='ENCODE', $key='', $expiry=0){
    if($key==''){
        $key=C('auth_key');
    }
    //如果steeze扩展存在，则使用扩展函数
    if(function_exists('steeze_sys_auth')){
        return steeze_sys_auth($str, $operation, $key, $expiry);
    }
	$operation=strtoupper($operation);
	$key_length=4;
	$key=md5($key);
	$fixedkey=md5($key);
	$egiskeys=md5(substr($fixedkey, 16, 16));
	$runtokey=$key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($str, 0, $key_length)) : '';
	$keys=md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
	$str=$operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($str . $egiskeys), 0, 16) . $str : base64_decode(substr($str, $key_length));
	
	$i=0;
	$result='';
	$strLen=strlen($str);
	for($i=0; $i < $strLen; $i++){
		$result.=chr(ord($str{$i}) ^ ord($keys{$i % 32}));
	}
	if($operation == 'ENCODE'){
		return $runtokey . str_replace('=', '', base64_encode($result));
	}else{
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $egiskeys), 0, 16)){
			return substr($result, 26);
		}else{
			return '';
		}
	}
}

/**
 * 系统加密解密的字符串
 *
 * @param string $str 需要处理的字符串
 * @param string $type 处理类型：1为加密，0为解密，默认为1
 * @param string $key 自定义秘钥，默认为空
 * @return string 处理后的数据
 */
function sys_crypt($str, $type=1, $key=''){
    if($key===''){
        $key=C('auth_key');
    }
    //如果steeze扩展存在，则使用扩展函数
    if(function_exists('steeze_sys_crypt')){
        $operation=$type ? 'encode' : 'decode';
        return steeze_sys_crypt($str, $operation, $key);
    }
	$keys=md5($key);
	$str=$type ? (string)$str : base64($str, 'decode', 1);
	$strLen=strlen($str);
	$result='';
	for($i=0; $i < $strLen; $i++){
		$result.=chr(ord($str{$i}) ^ ord($keys{$i % 32}));
	}
	return $type ? base64($result, 'encode', 1) : $result;
}

/**
 * 处理界面模板（包括管理后台、前台和插件）
 *
 * @param string $template 模板文件名，不包括扩展名（.html）
 * @param string|bool $dir 为string时为模板在目录，相对于模板目录；为bool时是否强制调用（true：后台模板，false:前台模板）
 * @param string|bool $style 为string时为使用的风格；为bool时是否强制调用（true：后台模板，false:前台模板）
 * @param string $module 模块名称
 * @param bool $isCompile 是否需要编译，如果为true，返回编译后的路径，否则返回模板路径
 * @return string 返回模板路径
 */
function template($template='index', $dir='', $style='', $module='', $isCompile=true){
	$tplExists=false;
	is_bool($dir) && ($dir='');
	is_bool($style) && ($style='');
	!is_string($module) && ($module='');
	
	if(strpos($template, '.') === false){
		$phpfile=$template . '.php';
		$template.='.html';
	}else{
		$phpfile=substr($template, 0, strrpos($template, '.')) . '.php';
	}
	
	$module=strtolower($module !== '' ? $module : env('ROUTE_M'));
	$dir=ucwords(str_replace('/', DS, $dir), DS);
	$style === '' && ($style=C('default_theme'));
	
	$templatefile=simplify_ds(APP_PATH . $module . DS . 'View' . DS . $style . DS . $dir . DS . $template);
	$tplExists=is_file($templatefile);
	// 调用默认模板
	if(!$tplExists){
		$templatefile=simplify_ds(APP_PATH . $module . DS . 'View' . DS . 'Default' . DS . $dir . DS . $template);
		$compiledtplfile=simplify_ds(CACHE_PATH . 'View' . DS . $module . DS . 'Default' . DS . $dir . DS . $phpfile);
		$tplExists=is_file($templatefile);
	}else{
		$compiledtplfile=simplify_ds(CACHE_PATH . 'View' . DS . $module . DS . $style . DS . $dir . DS . $phpfile);
	}
	
	if(!$isCompile){
		return $templatefile;
	}
	
	if($tplExists && (!is_file($compiledtplfile) || (filemtime($templatefile) > filemtime($compiledtplfile)) || (APP_DEBUG && defined('TEMPLATE_REPARSE') && TEMPLATE_REPARSE))){
		$templateService=Service\Template\Manager::instance();
		$templateService->compile($templatefile, $compiledtplfile);
	}
	return $compiledtplfile;
}



// //////////////////////////////////////////////////////////////
/**
 * ***********************字符串处理函数**********************
 */

/**
 * 将对象或数组转换为JSON字符串
 *
 * @param mixed $data 带转换的对象或数组
 * @param int $option 转换选项
 * @return string
 */
function to_string($data, $option=null){
    $isObject=false;
    if(is_array($data) || ($isObject=is_object($data))){
        $isModel=$isObject && is_a($data,'\Library\Model');
        //命令行模式下输出格式化的JSON
        $jsonOption=is_null($option) ? (env('PHP_SAPI','cli')!='cli' ? JSON_UNESCAPED_UNICODE:
                    (JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) : $option;
        return json_encode(
                $isModel ? $data->data() : $data,
                $jsonOption
            );
    }
    return strval($data);
}

/**
 * 字符串命名风格转换
 * 
 * @param string $name 字符串
 * @param integer $type 转换类型，0:输出C的风格（下划线分割）、1:输出Java的风格（驼峰式）
 * @return string
 */
function parse_name($name, $type=0){
	if($type){
		if(strpos($name, '_') !== false){
			$names=explode('_', $name);
			foreach($names as $k=>$v){
				if($k){
					$names[$k]=ucfirst(strtolower($v));
				}
			}
			return implode('', $names);
		}
        return $name;
	}else{
		return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
	}
}

/**
 * 取得文件扩展名（小写）
 *
 * @param string $filename 文件名
 * @param string $default 默认扩展名
 * @param int $maxExtLen 允许获取扩展名的最大长度
 * @return string 扩展名
 */
function fileext($filename, $default='jpg', $maxExtLen=5){
	if(strrpos($filename, '/') !== false){
		$filename=substr($filename, strrpos($filename, '/') + 1);
	}
	if(strpos($filename, '.') === false){
		return $default;
	}
	$ext=strtolower(trim(substr(strrchr($filename, '.'), 1)));
	return strlen($ext) > $maxExtLen ? $default : $ext;
}

/**
 * 从提供的字符串中，产生随机字符串
 *
 * @param int $length 输出长度
 * @param string $chars 范围字符串，默认为：0123456789
 * @return string 生成的字符串
 */
function random($length, $chars='0123456789'){
	$hash='';
	$max=strlen($chars) - 1;
	for($i=0; $i < $length; $i++){
		$hash.=$chars[mt_rand(0, $max)];
	}
	return $hash;
}

/**
 * 将字符串转换为数组
 *
 * @param string $data 需要转换的字符串
 * @return array
 */
function string2array($data){
	if(is_array($data)){
		return $data;
	}
	if($data == ''){
		return array();
	}
    $array=[];
	@eval("\$array = $data;");
	return $array;
}

/**
 * 将数组转换为字符串
 *
 * @param array $data
 * @param bool $isSlashes
 * @return string
 */
function array2string($data, $isSlashes=true){
	if($data == ''){
		return '';
	}
	if(!is_array($data)){
		return $data;
	}
	if($isSlashes){
		$data=slashes($data, 0);
	}
	return var_export($data, true);
}

/**
 * 将数组转换数据为字符串表示的形式，支持其中变量设置
 *
 * @param array $data
 * @return string
 */
function array2html($data){
	if(is_array($data)){
		$str='array(';
		foreach($data as $key=>$val){
			if(is_string($key)){
				$key='\'' . $key . '\'';
			}
			if(is_array($val)){
				$str.=$key . '=>' . array2html($val) . ',';
			}else{
				if(strpos($val, '$') === 0){
					$str.=$key . '=>' . $val . ',';
				}else{
					if(is_string($val)){
						if(strpos($val, '$') !== false){
							$val='"' . addslashes($val) . '"';
						}else{
							$val='\'' . addslashes($val) . '\'';
						}
					}
					$str.=$key . '=>' . $val . ',';
				}
			}
		}
		return $str . ')';
	}
	return false;
}

/**
 * 返回经addslashes处理过的字符串或数组
 *
 * @param string|array $data，字符串或数组对象
 * @param bool $isAdd 是否为添加slashes，默认为true
 * @return string|array
 */
function slashes($data, $isAdd=true){
	if(!is_array($data)){
		return $isAdd ? addslashes($data) : stripslashes($data);
	}
	foreach($data as $key=>$val){
		$data[$key]=slashes($val, $isAdd);
	}
	return $data;
}

/**
 * 转义 javascript 代码标记
 *
 * @param string $data 原始html字符串
 * @return string
 */
function trim_script($data){
	if(is_array($data)){
		foreach($data as $key=>$val){
			$data[$key]=trim_script($val);
		}
	}else{
		$data=preg_replace('/\<([\/]?)script([^\>]*?)\>/si', '&lt;\\1script\\2&gt;', $data);
		$data=preg_replace('/\<([\/]?)iframe([^\>]*?)\>/si', '&lt;\\1iframe\\2&gt;', $data);
		$data=preg_replace('/\<([\/]?)frame([^\>]*?)\>/si', '&lt;\\1frame\\2&gt;', $data);
		$data=preg_replace('/]]\>/si', ']] >', $data);
	}
	return $data;
}

/**
 * 安全过滤函数
 *
 * @param array|string $data
 * @return string
 */
function safe_replace($data){
	if(is_array($data)){
        foreach($data as $key=>$val){
			$data[$key]=safe_replace($val);
		}
	}else if(!empty($data)){
        return str_replace(
            array(
                '"', '<', '>',
                '%20','%27','%2527',
                '*',"'",'`',';',
                "{",'}','\\'
            ), 
            array(
                '&quot;', '&lt;', '&gt;'
            ), 
            $data
        );
    }
	return $data;
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root='root', $item='item', $attr='', $id='id', $encoding='utf-8') {
    if(is_array($attr)){
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr   = trim($attr);
    $attr   = empty($attr) ? '' : " {$attr}";
    $xml    = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml   .= "<{$root}{$attr}>";
    $xml   .= data_to_xml($data, $item, $id);
    $xml   .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item='item', $id='id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if(is_numeric($key)){
            $id && $attr = " {$id}=\"{$key}\"";
            $key  = $item;
        }
        $xml    .=  "<{$key}{$attr}>";
        $xml    .=  (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml    .=  "</{$key}>";
    }
    return $xml;
}

/**
 * 获取远程文件（自动支持GET/POST方法）
 *
 * @param string $url 文件地址或配置信息
 * @param array|string|int $data 数组为POST参数，字符串为保存路径，整数为请求超时
 * @param array|string|int $headers 数组为设置请求头信息，字符串为保存路径，整数为请求超时
 * @param string|int $savepath 字符串为文件保存路径，整数为请求超时
 * @param int $timeout 请求超时（单位：秒），默认5秒
 * @return string|int|null 如果设置了保存路径，则返回获取的字节大小，否则返回获取的内容
 * 
 * 使用说明<br/>
 * 1.本函数主要用于获取远程获取文件，如果是HTTP请求使用http_request函数。<br/>
 * 2.本函数如果不设置文件保持路径，会将文件内容返回<br/>
 * 3.本函数支持大文件下载，但要注意设置请求超时
 */
function get_remote_file($url, $data=null, $headers=null, $savepath=null, $timeout=5){
	if(is_string($data)){
		$savepath=$data;
        $data=null;
	}else if(is_int($data)){
        $timeout=$data;
        $data=null;
    }
	if(is_string($headers)){
		$savepath=$headers;
        $headers=null;
	}else if(is_int($headers)){
        $timeout=$headers;
        $headers=null;
    }
    if(is_int($savepath)){
        $timeout=$savepath;
        $savepath=null;
    }
    
    $config=array(
        'url'=> $url, 
        'data'=> $data,
        'headers'=> $headers,
        'timeout'=> $timeout
    );
    
    if(is_string($savepath)){
        $pathname=dirname($savepath);
	    !is_dir($pathname) && mkdir($pathname, 0777, true);
        $fp=fopen($savepath, 'w+');
        $config['output']=$fp;
        $result=http_request($config);
        fclose($fp);
        return $result;
    }else{
        return http_request($config);
    }
}

/**
 * HTTP请求
 *
 * @param array|string $config 参数配置或url
 * @param array|string $data POST参数（第1个参数为url时有效）
 * @param array $headers 请求头参数（第1个参数为url时有效）
 * @return string|int|null 如果参数设置了output选项，则返回获取的字节大小，否则返回获取的内容
 * 
 * 使用范例<br/>
 * 1、POST请求并输出返回值<br/>
 * <pre>
 * $result=http_request(array(
 *  'url'=>'http://steeze.com/message', //请求地址
 *  'data'=>array('name'=>'test'), //POST参数（可以为字符串或数组）
 *  'headers'=>array('TOKEN'=>'123'), //HEADER参数
 *  'method'=>'POST', //请求方法（默认：GET）
 *  'timeout'=>5, //超时（单位：秒）
 * ));
 * echo $result;
 * </pre>
 * 
 * 2、GET请求下载文件<br/>
 * <pre>
 * $fp=fopen('logo.png', 'w');
 * $result=http_request(array(
 *  'url'=>'https://steeze.cn/img/logonav.png', //下载文件地址
 *  'output'=> $fp, //POST参数（可以为字符串或资源类型）
 * ));
 * var_dump($result); //输出获取到的内容字节数
 * fclose($fp);
 * </pre>
 */
function http_request($config, $data=null, $headers=null){
    if(!is_array($config)){
        $config=array(
            'url'=> $config, 
            'data'=> $data, 
            'headers'=> $headers
        );
    }
    $url=isset($config['url']) ? trim($config['url']) : null;
    $data=isset($config['data']) ? $config['data'] : $data;
    $headers=isset($config['headers']) ? $config['headers'] : $headers;
    $method=isset($config['method']) ? $config['method'] : 'GET';
    $timeout=isset($config['timeout']) ? $config['timeout'] : 5;
    $output=isset($config['output']) ? $config['output'] : null;
    
	if(empty($url)){
		return null;
	}
    
	$ch=curl_init();
    
    //证书校验
	if(stripos($url, 's://') !== false){
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
	}
    
    //设置请求头
	curl_setopt($ch, CURLOPT_URL, $url);
	if(is_array($headers)){
		foreach($headers as $k=> $v){
			if(is_string($k)){
				$headers[$k]=$k.':'.$v;
			}
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
    
    //POST数据
	if(!empty($data)){
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}else{
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_MAXREDIRS,20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
    }
    
    //其它选项
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    if(is_resource($output)){
        curl_setopt($ch, CURLOPT_FILE, $output);
    }
    
	$result=curl_exec($ch);
	$httpCode=curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $httpSize=curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
	curl_close($ch);
	
	if($httpCode != 200){
		return null;
	}
    
    if(is_string($output)){
        $pathname=dirname($output);
	    !is_dir($pathname) && mkdir($pathname, 0777, true);
        return file_put_contents($output, $result);
    }else if(is_resource($output)){
        return $httpSize;
    }
    
    return $result;
}

/**
 * session管理函数
 *
 * @param string|array $name session名称 如果为数组则表示进行session设置
 * @param mixed $value session值
 * @return mixed
 */
function session($name='', $value=''){
	static $prefix=NULL;
	is_null($prefix) && ($prefix=C('session_prefix'));
	
	if(is_array($name)){ // session初始化 在session_start 之前调用
		isset($name['prefix']) && ($prefix=$name['prefix']);
        $key=isset($name['name']) ? $name['name'] : C('var_session_id','PHPSESSID');
		$sid=isset($name['id']) ? $name['id'] : env('SESSION_ID');
        
		!is_null($sid) && session_id($sid);
		ini_set('session.auto_start', 0);
		session_name($key);
		isset($name['path']) && session_save_path($name['path']);
		isset($name['domain']) && ini_set('session.cookie_domain', $name['domain']);
		if(isset($name['expire'])){
			ini_set('session.gc_maxlifetime', $name['expire']);
			ini_set('session.cookie_lifetime', $name['expire']);
		}
		isset($name['use_trans_sid']) && ini_set('session.use_trans_sid', $name['use_trans_sid'] ? 1 : 0);
		isset($name['use_cookies']) && ini_set('session.use_cookies', $name['use_cookies'] ? 1 : 0);
		isset($name['cache_limiter']) && session_cache_limiter($name['cache_limiter']);
		isset($name['cache_expire']) && session_cache_expire($name['cache_expire']);
		
		if($name['type']){ // 读取session驱动
			$class=ucwords(strtolower($name['type']));
			$path=KERNEL_PATH . 'Service' . DS . 'Session' . DS . 'Driver' . DS . $class . '.class.php';
			if(is_file($path)){
                // 配置驱动
				$hander=make('Service\\Session\\Driver\\' . $class);
                call_user_func(
                    'session_set_save_handler',
                    [&$hander,'open'], 
                    [&$hander,'close'], 
                    [&$hander,'read'], 
                    [&$hander,'write'], 
                    [&$hander,'destroy'], 
                    [&$hander,'gc']
                );
			}
		}
		// 启动session
        session('[start]');
	}elseif('' === $value){
		if('' === $name){
			// 获取全部的session
			return $prefix ? $_SESSION[$prefix] : $_SESSION;
		}elseif(0 === strpos($name, '[')){ // session 操作
			if('[pause]' == $name){ // 暂停session
				session_write_close();
			}elseif('[start]' == $name){ // 启动session
                //如果当前session处于活动状态先删除当前session
                if(session('[status]')==PHP_SESSION_ACTIVE){
                    session_unset();
				    session_destroy();
                }
                $sid=null;
                $key=C('var_session_id','PHPSESSID');
                $sid=env('SESSION_ID');
                !is_null($sid) && session_id($sid);
                ini_set('session.auto_start', 0);
                session_name($key);
				session_start();
			}elseif('[destroy]' == $name){ // 销毁session
				$_SESSION=array();
				session_unset();
				session_destroy();
			}elseif('[regenerate]' == $name){ // 重新生成id
				session_regenerate_id();
			}elseif('[id]' == $name){ // 返回id
				return session_id();
			}elseif('[name]' == $name){ // 返回name
				return session_name();
			}elseif('[status]' == $name){ //返回状态
                return session_status();
            }
		}elseif(0 === strpos($name, '?')){ // 检查session
			$name=substr($name, 1);
			if(strpos($name, '.')){ // 支持数组
				list($name1, $name2)=explode('.', $name, 2);
				return $prefix ? isset($_SESSION[$prefix][$name1][$name2]) : isset($_SESSION[$name1][$name2]);
			}else{
				return $prefix ? isset($_SESSION[$prefix][$name]) : isset($_SESSION[$name]);
			}
		}elseif(is_null($name)){ // 清空session
			if($prefix){
				unset($_SESSION[$prefix]);
			}else{
				$_SESSION=array();
			}
		}elseif($prefix){ // 获取session
			if(strpos($name, '.')){
				list($name1, $name2)=explode('.', $name, 2);
				return isset($_SESSION[$prefix][$name1][$name2]) ? $_SESSION[$prefix][$name1][$name2] : null;
			}else{
				return isset($_SESSION[$prefix][$name]) ? $_SESSION[$prefix][$name] : null;
			}
		}else{
			if(strpos($name, '.')){
				list($name1, $name2)=explode('.', $name, 2);
				return isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
			}else{
				return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
			}
		}
	}elseif(is_null($value)){ // 删除session
		if(strpos($name, '.')){
			list($name1, $name2)=explode('.', $name, 2);
			if($prefix){
				unset($_SESSION[$prefix][$name1][$name2]);
			}else{
				unset($_SESSION[$name1][$name2]);
			}
		}else{
			if($prefix){
				unset($_SESSION[$prefix][$name]);
			}else{
				unset($_SESSION[$name]);
			}
		}
	}else{ // 设置session
		if(strpos($name, '.')){
			list($name1, $name2)=explode('.', $name);
			if($prefix){
				$_SESSION[$prefix][$name1][$name2]=$value;
			}else{
				$_SESSION[$name1][$name2]=$value;
			}
		}else{
			if($prefix){
				$_SESSION[$prefix][$name]=$value;
			}else{
				$_SESSION[$name]=$value;
			}
		}
	}
	return null;
}

/**
 * 导入静态文件路径 如：'js/show.js@daoke:home'，则返回/assets/app/home/daoke/js/show.js
 *
 * @param string $file 文件模式路径
 * @param string $type 文件类型
 * @param bool $check 是否检查存在，如果不存在返回空
 * @param string $default 如果不存在，默认的风格包名称
 */
function assets($file, $type='', $check=false, $default='default'){
	if(!is_string($type)){
		if(is_string($check)){
			$default=$check;
		}
		$check=boolval($type);
		$type='';
	}
	
	$isBase=strpos($file, '#')===0;
	if($isBase){
		$file=substr($file, 1);
	}
	
	if(strpos($file, '://') !== false){
		return $file;
	}
	
    // 相对文件路径
	if($file[0] != '/'){
		$module='';
		$style='';
		if(strpos($file, '@') != false){
			$styles=explode('@', $file, 2);
			$file=$styles[0];
			$styles=explode(':', $styles[1], 2);
			if(count($styles) == 1){
				$style=$styles[0];
				$module=env('ROUTE_M');
			}else{
				$style=$styles[0];
				$module=$styles[1];
			}
		}else{
			$module=env('ROUTE_M');
			$style=C('default_assets');
		}
		
		if($file === ''){
			return '';
		}
        
        // 检查自定义风格是否为外部地址
		$isExtern=strpos($style, '://') !== false;
		
		// 单个文件名的js/css/images文件自动识别上级目录
		if(!$isBase && strpos($file, '/')===false){
            $typeDir='';
            if($type===''){
                $type=fileext(strpos($file, '?')!==false ? strstr($file,'?',true) : $file);
            }
			if($type == 'js' || $type == 'css'){
				$typeDir=$type.'/';
			}elseif($type == 'jpg' || $type == 'png' || $type == 'gif' || $type == 'jpeg' || $type == 'bmp'){
				$typeDir='images/';
			}
            
            //如果上级目录识别成功，并且文件不包含上级目录则自动附加上级目录
            if(!empty($typeDir) && strpos($file, $typeDir)!==0){
                $file=$typeDir.$file;
            }
		}
		
		if($style != '/'){
			$style=rtrim($style, '/');
		}
        
        // 外部地址直接返回
        if($isExtern){
            return $style . '/' . $file;
        }
		
        $module=strtolower($module);
        
        // 检查文件是否存在
		if($check && !$isExtern){
			if(strpos($style, '/') === 0){
                // 绝对路径的检查
				$style=ltrim($style, '/');
                if(is_file(ASSETS_PATH . ($style != '' ? $style . DS : '') . $file)){
                    return env('ASSETS_URL') . ($style != '' ? $style . '/' : '') . $file ;
                }
			}else{
                // 相对路径的检查，不存在使用默认风格
				if(!is_file(ASSETS_PATH . $module . DS . ($style === '' ? '' : $style . DS) . $file)){
                    if(is_file(ASSETS_PATH . 'app' . DS . $module . DS . $default . DS . $file)){
                        return env('ASSETS_URL') . 'app/' . $module . '/' . trim($default, '/') . '/' . $file;
                    }
				}
			}
            return '';
		}
        
        // 绝对路径和相对风格路径
        if(strpos($style, '/') === 0){
            $style=$style != '/' ? ltrim($style, '/') . '/' : '';
        }else{
            $style='app/' . $module . '/' . ($style === '' ? '' : $style . '/');
        }
		return env('ASSETS_URL') . $style . $file;
	}
    
    // 绝对文件路径
    return env('ASSETS_URL') . ltrim($file, '/');
}

/**
 * 从容器中返回给定类型名称的实例
 *
 * @param string $concrete 类型名称
 * @param array $parameters 参数
 * @param \Library\Container $container 使用的容器对象，为null则使用系统容器
 * @return object 类型实例
 */
function make($concrete, $parameters=[], $container=null){
    if(is_object($parameters)){
        $container=&$parameters;
    }
    if(is_null($container)){
	    $container=\Library\Container::getInstance();
    }
	return $container->make($concrete, is_array($parameters) ? $parameters : []);
}

/**
 * 获取环境变量（键名不区分大小写）
 * 
 * @param string $key 键名称
 * @param string $default 默认值
 * @return string 环境变量值
 */
function env($key, $default=null){
	return Loader::env($key, null, $default);
}

/**
 * 记录和统计时间（微秒）和内存使用情况 
 * 
 * 使用方法: 
 * <code>
 * G('begin'); // 记录开始标记位
 * G('end');   //记录结束标签位
 * echo G('begin','end',6);  //统计区间运行时间 精确到小数后6位
 * echo G('begin','end','m');  //统计区间内存使用情况
 * </code>
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效 
 * 
 * @param string $start 开始标签
 * @param string $end 结束标签
 * @param int|string $dec 小数位或者m
 * @return mixed
 */
function G($start, $end='', $dec=4){
	static $_info=array();
	static $_mem=array();
	if(is_float($end)){ // 记录时间
		$_info[$start]=$end;
	}elseif(!empty($end)){ // 统计时间和内存使用
		if(!isset($_info[$end]))
			$_info[$end]=microtime(TRUE);
		if(defined('MEMORY_LIMIT_ON') && constant('MEMORY_LIMIT_ON') && $dec == 'm'){
			if(!isset($_mem[$end]))
				$_mem[$end]=memory_get_usage();
			return number_format(($_mem[$end] - $_mem[$start]) / 1024);
		}else{
			return number_format(($_info[$end] - $_info[$start]), $dec);
		}
	}else{ // 记录时间和内存使用
		$_info[$start]=microtime(TRUE);
		if(defined('MEMORY_LIMIT_ON') && constant('MEMORY_LIMIT_ON'))
			$_mem[$start]=memory_get_usage();
	}
	return null;
}

/**
 * 缓存管理
 * 
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value 缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 */
function S($name, $value='', $options=null){
	static $cache='';
	if(is_array($options)){
		// 缓存操作的同时初始化
		$type=isset($options['type']) ? $options['type'] : '';
		$cache=Service\Cache\Manager::getInstance($type, $options);
	}elseif(is_array($name)){ // 缓存初始化
		$type=isset($name['type']) ? $name['type'] : '';
		$cache=Service\Cache\Manager::getInstance($type, $name);
		return $cache;
	}elseif(empty($cache)){ // 自动初始化
		$cache=Service\Cache\Manager::getInstance();
	}
	if('' === $value){ // 获取缓存
		return $cache->get($name);
	}elseif(is_null($value)){ // 删除缓存
		return $cache->rm($name);
	}else{ // 缓存数据
		if(is_array($options)){
			$expire=isset($options['expire']) ? $options['expire'] : NULL;
		}else{
			$expire=is_numeric($options) ? $options : NULL;
		}
		return $cache->set($name, $value, $expire);
	}
}

/**
 * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
 * 
 * @param string $name 缓存名称
 * @param mixed $value 缓存值
 * @param string $path 缓存路径
 * @return mixed
 */
function F($name, $value='', $path=null){
	static $_cache=array();
	if(is_null($path)){
		$path=CACHE_PATH . 'Data' . DS;
	}
	$filename=$path . $name . '.php';
	
	// 初始化存储服务
	Service\Storage\Manager::connect(STORAGE_TYPE);
	
	if('' !== $value){
		if(is_null($value)){
			// 删除缓存
			if(false !== strpos($name, '*')){
				return false; // TODO
			}else{
				unset($_cache[$name]);
				return Service\Storage\Manager::unlink($filename, 'F');
			}
		}else{
			Service\Storage\Manager::put($filename, serialize($value), 'F');
			// 缓存数据
			$_cache[$name]=$value;
			return null;
		}
	}
	// 获取缓存数据
	if(isset($_cache[$name])){
		return $_cache[$name];
	}
	if(Service\Storage\Manager::has($filename, 'F')){
		$value=unserialize(Service\Storage\Manager::read($filename, 'F'));
		$_cache[$name]=$value;
	}else{
		$value=false;
	}
	return $value;
}

/**
 * 模型快速操作
 * 
 * <pre>
 * $conn参数为字符串类型时举例说明： 
 * 1、"xxx": 使用"xxx"为连接名称，表前缀使用连接配置； 
 * 2、"^aaa_@xxx"（或"^@xxx"）: 使用"aaa_"（或为空）为表前缀,xxx为连接名称； 
 * 3、"^aaa_"（或"^"）: 使用"aaa_"（或为空）为表前缀，连接名称使用系统默认配置
 * </pre>
 *
 * @param string $name 需要操作的表
 * @param mixed $conn 为字符串时，如果以"^xxx"开头，表示表前缀，否则表示数据库配置名；如果为数组，表示配置
 * @return \Library\Model 数据库Model模型对象 
 */
function M($name='', $conn=''){
	static $_model=array();
	$tablePrefix=''; // 使用连接配置
	if(is_string($conn) && strpos($conn, '^') === 0){
		$conns=explode('@', trim($conn, '^'), 2);
		// 如果为"^@xxx"或"^"则不使用前缀
		$tablePrefix=$conns[0] !== '' ? $conns[0] : null;
		// "^@xxx"或"^aaa@xxx"则使用xxx为连接名;"^"或"^aaa"则连接名使用系统配置
		$conn=count($conns) > 1 ? $conns[1] : '';
	}
	$guid=(is_array($conn) ? implode('', (array)$conn) : $conn) . $tablePrefix . $name;
	if(!isset($_model[$guid])){
		$_model[$guid]=new Library\Model($name, $tablePrefix, $conn);
	}
	return $_model[$guid];
}

/**
 * 获取系统配置信息
 *
 * @param string $key 获取配置的名称，可以使用“配置名.键名1.键名2”的格式
 * @param string $default 默认值
 * @return mixed 配置信息
 */
function C($key='', $default=''){
	if(is_string($key)){
		$keys=explode('.', $key);
		count($keys) < 2 && array_unshift($keys, 'system');
		$len=count($keys);
		$file=trim($keys[0]);
		$key=trim($keys[1], ' *');
		
		if($len < 3){
			return Loader::config($file, $key, $default);
		}else{
			$res=Loader::config($file, $key, $default);
			if(is_array($res)){
				for($i=2; $i < $len; $i++){
					if($keys[$i]=='*'){
						break;
					}
					if(!is_array($res) || !isset($res[$keys[$i]])){
						$res=$default;
						break;
					}
					$res=$res[$keys[$i]];
				}
			}
			return $res;
		}
	}elseif(is_array($key)){
		return Loader::config((empty($default) ? 'system' : $default), $key);
	}
	return null;
}


/**
 * 生成错误异常 
 * 
 * @param mixed $error 参数名称 
 * @param int|array $code 错误码，也可以传入一个包括code键的数组 
 * @return \Exception
 */
function E($error, $code=404){
	$errorCode=intval(is_array($code) && isset($code['code']) ? $code['code'] : $code);
	if(!is_object($error)){
		throw new \Exception(strval($error), $errorCode);
	}else if($error instanceof \Exception){
		throw $error;
	}else{
		throw new \Exception(L('Error: '.get_class($error)), $errorCode);
	}
}

/** 
 * 语言转化
 * 
 * 使用范例：
 * L('hello: {name}', ['name'=>'steeze'])
 *  
 * @param string $message 语言键名
 * @param array $datas 参数变量数组
 * @return string 转换后的语言
 */
function L($message, $datas=array()){
	static $langs=null;
	if(is_null($langs)){
		$langs=array();
		$lang=DS . 'Lang' . DS . C('lang', 'zh-cn') . '.php';
		if(is_file(KERNEL_PATH . $lang)){
			$langs=array_merge($langs, include_once (KERNEL_PATH . $lang));
		}
		$app_path=simplify_ds(APP_PATH . env('ROUTE_M', '') . $lang);
		if(is_file($app_path)){
			$langs=array_merge($langs, include_once ($app_path));
		}
	}
	$message=isset($langs[$message]) ? $langs[$message] : $message;
	foreach((array)$datas as $k=>$v){
		$message=str_replace('{' . $k . '}', $v, $message);
	}
	return $message;
}
