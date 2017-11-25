<?php
namespace Library;

class Http{
	var $method;
	var $cookie;
	var $post;
	var $header;
	var $ContentType;
	var $errno;
	var $errstr;

	function __construct(){
		$this->method='GET';
		$this->cookie='';
		$this->post='';
		$this->header='';
		$this->errno=0;
		$this->errstr='';
	}

	function post($url,$data=array(),$referer='',$limit=0,$timeout=30,$block=TRUE){
		$this->method='POST';
		$this->ContentType="Content-Type: application/x-www-form-urlencoded\r\n";
		if($data){
			$post='';
			foreach($data as $k=>$v){
				$post.=$k . '=' . rawurlencode($v) . '&';
			}
			$this->post.=substr($post, 0, -1);
		}
		return $this->request($url, $referer, $limit, $timeout, $block);
	}

	function get($url,$referer='',$limit=0,$timeout=30,$block=TRUE){
		$this->method='GET';
		return $this->request($url, $referer, $limit, $timeout, $block);
	}

	function upload($url,$data=array(),$files=array(),$referer='',$limit=0,$timeout=30,$block=TRUE){
		$this->method='POST';
		$boundary="AaB03x";
		$this->ContentType="Content-Type: multipart/form-data; boundary=$boundary\r\n";
		if($data){
			foreach($data as $k=>$v){
				$this->post.="--$boundary\r\n";
				$this->post.="Content-Disposition: form-data; name=\"" . $k . "\"\r\n";
				$this->post.="\r\n" . $v . "\r\n";
				$this->post.="--$boundary\r\n";
			}
		}
		foreach($files as $k=>$v){
			$this->post.="--$boundary\r\n";
			$this->post.="Content-Disposition: file; name=\"$k\"; filename=\"" . basename($v) . "\"\r\n";
			$this->post.="Content-Type: " . $this->get_mime($v) . "\r\n";
			$this->post.="\r\n" . file_get_contents($v) . "\r\n";
			$this->post.="--$boundary\r\n";
		}
		$this->post.="--$boundary--\r\n";
		return $this->request($url, $referer, $limit, $timeout, $block);
	}

	function request($url,$referer='',$limit=0,$timeout=30,$block=TRUE){
		$matches=parse_url($url);
		$host=$matches['host'];
		$path=$matches['path'] ? $matches['path'] . ($matches['query'] ? '?' . $matches['query'] : '') : '/';
		$port=$matches['port'] ? $matches['port'] : 80;
		if($referer == '')
			$referer=URL;
		$out="$this->method $path HTTP/1.1\r\n";
		$out.="Accept: */*\r\n";
		$out.="Referer: $referer\r\n";
		$out.="Accept-Language: zh-cn\r\n";
		$out.="User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\r\n";
		$out.="Host: $host\r\n";
		if($this->cookie)
			$out.="Cookie: $this->cookie\r\n";
		if($this->method == 'POST'){
			$out.=$this->ContentType;
			$out.="Content-Length: " . strlen($this->post) . "\r\n";
			$out.="Cache-Control: no-cache\r\n";
			$out.="Connection: Close\r\n\r\n";
			$out.=$this->post;
		}else{
			$out.="Connection: Close\r\n\r\n";
		}
		if($timeout > ini_get('max_execution_time'))
			@set_time_limit($timeout);
		$fp=@fsockopen($host, $port, $errno, $errstr, $timeout);
		if(!$fp){
			$this->errno=$errno;
			$this->errstr=$errstr;
			return false;
		}else{
			stream_set_blocking($fp, $block);
			stream_set_timeout($fp, $timeout);
			fwrite($fp, $out);
			$this->data='';
			$status=stream_get_meta_data($fp);
			if(!$status['timed_out']){
				$maxsize=min($limit, 1024000);
				if($maxsize == 0)
					$maxsize=1024000;
				$start=false;
				while(!feof($fp)){
					if($start){
						$line=fread($fp, $maxsize);
						if(strlen($this->data) > $maxsize)
							break;
						$this->data.=$line;
					}else{
						$line=fgets($fp);
						$this->header.=$line;
						if($line == "\r\n" || $line == "\n")
							$start=true;
					}
				}
			}
			fclose($fp);
			return $this->is_ok();
		}
	}

	function save($file){
		Loader::helper('dir');
		dir_create(dirname($file));
		return file_put_contents($file, $this->data);
	}

	function set_cookie($name,$value){
		$this->cookie.="$name=$value;";
	}

	function get_cookie(){
		$cookies=array();
		if(preg_match_all("|Set-Cookie: ([^;]*);|", $this->header, $m)){
			foreach($m[1] as $c){
				list($k, $v)=explode('=', $c);
				$cookies[$k]=$v;
			}
		}
		return $cookies;
	}

	function get_data(){
		if(strpos($this->header, 'chunk')){
			$data=explode(chr(13), $this->data);
			return $data[1];
		}else{
			return $this->data;
		}
	}

	function get_header(){
		return $this->header;
	}

	function get_status(){
		preg_match("|^HTTP/1.1 ([0-9]{3}) (.*)|", $this->header, $m);
		return array($m[1],$m[2]);
	}

	function get_mime($file){
		$ext=strtolower(trim(substr(strrchr($file, '.'), 1, 10)));
		return $ext == '' ? '' : C('mimetype.'.$ext);
	}

	function is_ok(){
		$status=$this->get_status();
		if(intval($status[0]) != 200){
			$this->errno=$status[0];
			$this->errstr=$status[1];
			return false;
		}
		return true;
	}

	function errno(){
		return $this->errno;
	}

	function errmsg(){
		return $this->errstr;
	}
}
