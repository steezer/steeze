#!/usr/bin/env php
<?php
include dirname(__FILE__).'/../../kernel/base.php';

/**
 * Chrome crx 解析器，用于获取扩展、皮肤ID
 */

use Library\CommandColor;

class CrxParser {
	const MAX_PUBLIC_KEY_SIZE = 65535;
	const MAX_SIGNATURE_SIZE  = 65535;
	const HEADER_MAGIC_PREFIX = 'Cr24';
	const CURRENT_VERSION     = 2;
	
	private $fp = null; //文件指针
	private $filename = ''; //文件路径
	private $_header = array(); //crx 文件的头信息
	
	public function __construct($filename){
		$this->parse($filename);
	}
	
	/**
	 * 获取此应用的ID
	 * @return string
	 */
	public function getAppid() {
		$hash = hash('sha256',$this->_key);
		$hash = substr($hash,0,32);
		
		$length = strlen($hash);
		$ascii_0 = ord('0');
		$ascii_9 = ord('9');
		$ascii_a = ord('a');
		$data = '';
		for($i=0;$i<$length;$i++) {
			$c = ord($hash[$i]);
			
			if($c >= $ascii_0 && $c <= $ascii_9) {
				$d = chr($ascii_a + $c - $ascii_0);
			} else if($c >= $ascii_a && $c < $ascii_a + 6) {
				$d = chr($ascii_a + $c - $ascii_a + 10);
			} else {
				$d = 'a';
			}
			$data .= $d;
		}
		return $data;
	}
	
	/**
	 * 从crx文件中获取manifest.json文件的配置信息
	 * @param string $key
	 * @return mixed[]
	 */
	function getConfig($key=null){
		$zip_file=tempnam(dirname($this->filename),'zip');
		$manifest_arr=array();
		if($this->convertToZip($zip_file)){
			$zip=zip_open($zip_file);
			if(is_resource($zip)){
				while($zip_entry=zip_read($zip)){
					$entry_name=zip_entry_name($zip_entry);
					if(preg_match('/manifest\.json$/', $entry_name)){
						$content=zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
						$content_j=json_decode($content, true);
						if(!empty($content_j)){
							$manifest_arr=$content_j;
						}
					}
				}
			}
			zip_close($zip);
			unlink($zip_file);
		}
		return is_null($key) ? $manifest_arr : $manifest_arr[$key];
	}
	
	/**
	 * 将文件转换为zip文件
	 * @param string $target_path
	 */
	public function convertToZip($target_path=null){
		$offset=16+$this->_header['key_size']+$this->_header['sig_size'];
		$data=$this->getContent($this->filename,$offset);
		return !is_null($target_path) ? file_put_contents($target_path, $data) : $data;
	}
	
	/**
	 * 开始解析该 crx 文件
	 */
	private function parse($filename) {
		if(strpos($filename, '://')!==false && !file_exists($filename)) {
			throw new Exception("parser init: crx file does not exisit");
		}
		$this->filename=$filename;
		$this->fp = fopen($filename, 'r');
		$this->parse_header(); // 解析头部信息
		$this->parse_key();
		$this->parse_sig();
		fclose($this->fp);
	}
	
	/**
	 * 解析头部信息，并设置 $_header 数组
	 * @throws Exception 解析错误抛出异常
	 */
	private function parse_header() {
		$data = fread($this->fp, 16); // HEADER 头信息有16个字节
		if($data) {
			$data = @unpack('C4prefix/Vversion/Vkey_size/Vsig_size',$data);
		}else{
			throw new Exception("header parse: error reading header");
		}
		// 前四个字节拼合 prefix
		$data['prefix'] = chr( $data['prefix1'] ).chr( $data['prefix2'] ).chr( $data['prefix3'] ).chr( $data['prefix4'] );
		unset($data['prefix1'],$data['prefix2'],$data['prefix3'],$data['prefix4']);
		
		if($data['prefix'] != self::HEADER_MAGIC_PREFIX) {
			throw new Exception("header parse: illegal prefix");
		}
		if( $data['version'] != self::CURRENT_VERSION ) {
			throw new Exception("header parse: illegal version");
		}
		if(
				empty($data['key_size']) || $data['key_size'] > self::MAX_PUBLIC_KEY_SIZE ||
				empty($data['sig_size']) || $data['sig_size'] > self::MAX_SIGNATURE_SIZE
				){
					throw new Exception("header parse: illegal public key size or signature size");
		}
		$this->_header = $data;
	}
	
	/**
	 * 解析key
	 * @throws Exception
	 */
	private function parse_key() {
		$key = fread($this->fp,$this->_header['key_size']);
		if($key) {
			$this->_key = $key;
		}else{
			throw new Exception("key parse: error reading key");
		}
	}
	
	/**
	 * 解析sig
	 * @throws Exception
	 */
	private function parse_sig() {
		$sig = fread($this->fp,$this->_header['sig_size']);
		if($sig) {
			$this->_sig = $sig;
		}else{
			throw new Exception("sig parse: error reading sig");
		}
	}
	
	/**
	 * 从文件中获取指定位置及大小的内容
	 * @param string $filename
	 * @param number $offset
	 * @param int $length
	 * @return string
	 */
	private function getContent($filename,$offset=0,$length=-1){
		$stream = fopen($filename, 'rb');
		$content = stream_get_contents($stream, $length, $offset);
		fclose($stream);
		return $content;
	}
}


if(count($argv)<2){
	echo CommandColor::get('Usage: crx2zip filename [target]','cyan')."\n";
	echo "This is a tool for convert crx to zip\n";
}else if(is_file($filename=$argv[1])){
	$tagetfile=!isset($argv[2]) ? $filename : $argv[2];
	$target=dirname($tagetfile).'/'.basename($tagetfile,'.crx').'.zip';
	if(is_file($target)){
		echo CommandColor::get('target "'.$target.'" exists! please specify another','red')."\n";
	}else{
		$crxParser=new CrxParser($filename);
		$crxParser->convertToZip($target);
		if(is_file($target) && filesize($target) >0){
			echo CommandColor::get('Convert success!','green')."\n";
			echo CommandColor::get('save to: '.$target)."\n";
		}else{
			echo CommandColor::get('Convert failed!')."\n";
		}
	}
}else{
	echo CommandColor::get('Crx file "'.$filename.'" not exists!','red')."\n";
}

