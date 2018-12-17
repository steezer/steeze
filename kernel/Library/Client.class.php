<?php
namespace Library;

/**
 * 用于获取客户端信息
 * @author xiechunping
 **/
class Client{
	// 获得访客浏览器类型
	static function getBrowser(){
		if(!empty($_SERVER['HTTP_USER_AGENT'])){
			$br=$_SERVER['HTTP_USER_AGENT'];
			if(preg_match('/MSIE/i', $br)){
				$br='MSIE';
			}elseif(preg_match('/Firefox/i', $br)){
				$br='Firefox';
			}elseif(preg_match('/Chrome/i', $br)){
				$br='Chrome';
			}elseif(preg_match('/Safari/i', $br)){
				$br='Safari';
			}elseif(preg_match('/Opera/i', $br)){
				$br='Opera';
			}else{
				$br='Other';
			}
			return $br;
		}else{
			return "unknow";
		}
	}
	
	// 获得访客浏览器语言
	static function getLang(){
		if(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			$lang=$_SERVER['HTTP_ACCEPT_LANGUAGE'];
			$lang=trim(substr($lang, 0, 5));
			if(preg_match("/zh-cn/i", $lang)){
				$lang='zh-cn';
			}elseif(preg_match("/zh/i", $lang)){
				$lang='zh';
			}
			return $lang;
		}else{
			return '';
		}
	}
	
	// 获取访客操作系统
	static function getOS(){
		if(!empty($_SERVER['HTTP_USER_AGENT'])){
			$OS=$_SERVER['HTTP_USER_AGENT'];
			if(preg_match('/win/i', $OS)){
				$OS='Windows';
			}elseif(preg_match('/mac/i', $OS)){
				$OS='MAC';
			}elseif(preg_match('/linux/i', $OS)){
				$OS='Linux';
			}elseif(preg_match('/unix/i', $OS)){
				$OS='Unix';
			}elseif(preg_match('/bsd/i', $OS)){
				$OS='BSD';
			}else{
				$OS='Other';
			}
			return $OS;
		}else{
			return "unknow";
		}
	}
	
	// 获得访客真实ip
	static function getIpAddr($isOnline=0){
		if(getenv('HTTP_CLIENT_IP')){
			$ip=getenv('HTTP_CLIENT_IP');
		}else if(getenv('HTTP_X_FORWARDED_FOR')){
			$ip=getenv('HTTP_X_FORWARDED_FOR');
		}else if(getenv('REMOTE_ADDR')){
			$ip=getenv('REMOTE_ADDR');
		}else if(!empty($_SERVER["HTTP_CLIENT_IP"])){
			$ip=$_SERVER["HTTP_CLIENT_IP"];
		}
		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){ // 获取代理ip
			$ips=explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		}else{
            $ips=[];
        }
        if($ip){
            array_unshift($ips, $ip);
        }
		$count=count($ips);
		for($i=0; $i < $count; $i++){
			if(!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i])){ // 排除局域网ip
				$ip=$ips[$i];
				break;
			}
		}
		return preg_match("/^(10|172\.16|192\.168|127\.0)\./i", $ip) && $isOnline ? self::getOnlineIp() : $ip; // 获得本地真实IP
	}
	
	// 获得本地真实IP
	static function getOnlineIp(){
		$ip_json=get_remote_file('http://ip.taobao.com/service/getIpInfo.php?ip=myip');
		if(!$ip_json){return '';}
		$ip_arr=json_decode($ip_json, 1);
		if($ip_arr['code'] == 0){
			return $ip_arr['data']['ip'];
		}
	}
	
	// 根据ip获得访客所在地地名
	static function getIpFrom($ip=''){
		if(empty($ip)){
			$ip=self::getIpAddr();
		}
		$ip_json=get_remote_file('http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip); // 根据taobao ip
		if(!$ip_json){return '';}
		$ip_arr=json_decode($ip_json, 1);
		return $ip_arr['code'] == 0 ? $ip_arr : false;
	}
}