#!/usr/bin/env php
<?php
include dirname(__FILE__).'/../../kernel/base.php';
/**
 * 网页采集工具
 */

use Library\CommandColor;

class WebSpider{
	
	private $rootPath=null;//存放目录地址
	private $staticPath=null;//静态文件路径
	private $url=null;//采集的页面地址
	
	public function __construct($rootPath=null,$staticPath='assets/'){
		$this->rootPath=$rootPath && $rootPath!=='' ? rtrim($rootPath,'/').'/' : '';
		$this->staticPath=$staticPath && $staticPath!=='' ? rtrim($staticPath,'/').'/' : '';
	}
	
	/**
	 * 开始运行 
	 */
	public function start(){
		$shortopts  = 's:u:d:';
		$longopts = ['save:','url:','deep:'];
		if($options = getopt($shortopts,$longopts)){
			$url=isset($options['u']) ? $options['u'] : (isset($options['url'])?$options['url']:'');
			if(strpos($url, '://')){
				$this->url=$url;
				$path=isset($options['s']) ? $options['s'] : (isset($options['save'])?$options['save']:'');
				$deep=max(intval(isset($options['d']) ? $options['d'] : (isset($options['deep'])?$options['deep']:2)),2);
				$this->getHtmlFile($url,$path,$deep);
			}else{
				echo CommandColor::get('Please give a url address with "http://" prefix','red')."\n";
			}
		}else{
			echo $this->help();
		}
	}
	
	/**
	 * 帮助
	 */
	private function help(){
		$str="
			".CommandColor::get('This tool is help you to get page from website','green')."
			Usage: spider [option]
			option:
				-u | --url  The url address to be getted down (".CommandColor::get('Required','red').")
				-s | --save  The dirname you want to save, default is current dir
				-d | --deep  The deepth for page,default is \"2\"
		";
		return trim(str_replace(["\t\t\t","\t"], ['','  '], $str))."\n\n";
	}
	
	
	/**
	 * 本地化远程网页
	 * @param string $url 网址
	 * @param string $rootPath 根目录地址
	 * @param number $deep 深度
	 * @return boolean
	 */
	private function getHtmlFile($url,$rootPath='',$deep=1){
		if(is_int($rootPath)){
			$deep=$rootPath;
			$rootPath='';
		}
		if(!strpos($url,'://')){
			return false;
		}
		$deep=max($deep,1);//最小值为1
		
		$path=$this->formatPath($url);
		
		$base_path=$host_name=$rootPath;
		if(strpos($url,'://')){
			$spos=strpos($url,'/',strpos($url,'://')+3);
			$rootPath=$spos!==false ? substr($url,0,$spos+1) : $url.'/';
			$base_path=$rootPath.substr($path,1,strrpos($path,'/'));
			$host_name=strtolower(rtrim(substr($rootPath,strpos($rootPath,'://')+3),'/'));
		}
		
		$path=$this->rootPath.str_replace('/','_',ltrim($path,'/'));
		
		if(is_file($path)){
			return false;
		}
		$dirname=dirname($path);
		!is_dir($dirname) && mkdir($dirname,0777,true);
		
		$contents=$this->sendRequest($url);
		
		if(!is_null($contents)){
			$this->log($deep.':'.$url."\r\n",'html');
			$contents=$this->getStaticFile($contents,$base_path);
			
			$deep--;
			$content_urls=array();
			//从a标签中寻找待处理链接地址
			preg_match_all('/<a\s+[^<>]*href=["\']([^"\']+)["\']/i', $contents, $matches);
			foreach($matches[1] as $k=> $curl){
				if($curl!=='' && substr($curl,0,1)!='#' && stripos($curl, 'javascript:')===false){
					if(strpos($curl,'://')===false){//判断是否是相对路径，还是绝对路径
						$curl=strpos($curl,'/')===0 ? $rootPath.ltrim($curl,'/') : $base_path.$curl;
					}else{
						//如果为站外连接则不处理
						if(strtolower(parse_url($curl,PHP_URL_HOST))!=$host_name){
							unset($matches[0][$k],$matches[1][$k]);
							continue;
						}
					}
					
					if($deep>0){//替换新的URL
						$content_urls[]=$curl;
						$matches[1][$k]=str_replace($matches[1][$k],$this->formatPath($curl,true),$matches[0][$k]);
					}else{//将URL替换为外链绝对地址
						$matches[1][$k]=str_replace($matches[1][$k],$curl,$matches[0][$k]);
					}
				}else{
					unset($matches[0][$k],$matches[1][$k]);
				}
			}
			$contents=str_replace($matches[0],$matches[1],$contents);
			
			//写入文件内容
			file_put_contents($path, $contents);
			
			//递归获取文件内容
			$content_urls=array_unique($content_urls);
			foreach($content_urls as $curl){
				$this->getHtmlFile($curl,$rootPath,$deep);
			}
			return true;
		}
		return false;
	}
	
	/**
	 * 从HTML内容中获取外部链接文件
	 * @param string $contents
	 * @param string $rootPath
	 * @param string $type
	 * @return string[]
	 */
	private function getStaticFile($contents,$base_path='',$type=null){
		$preg_configs=array(
				'css'=>'/<link\s+.*?href=["\'](.*?)["\']/i',
				'js'=>'/<script\s+.*?src=["\'](.*?)["\']/i',
				'img'=>'/<img\s+.*?src=["\'](.*?)["\']/i',
				'other'=>'/url\(["\']?(.*?)["\']?\)/i',
		);
		
		if(empty($type)){
			//unset($preg_configs['other']);
			foreach(array_keys($preg_configs) as $type){
				$contents=$this->getStaticFile($contents,$base_path,$type);
			}
			return $contents;
		}
		
		$base_root=$base_path ? substr($base_path,0,strpos($base_path,'/',strpos($base_path,'://')+3)+1) : '';
		
		preg_match_all($preg_configs[$type], $contents, $matches);
		
		$replaces=array();
		$searches=array_unique($matches[1]);
		unset($matches);
		foreach($searches as $k=> $url){
			if($url!==''){
				$urls=parse_url($url);
				
				$domain=isset($urls['host'])&&$urls['host'] ? str_replace('.', '_', $urls['host']) : '';//域名
				
				if(!$domain){ //不带域名的绝对路径与相对路径的处理
					$url=$this->normalizePath(strpos($urls['path'], '/')===0 ? $base_root.ltrim($urls['path'],'/') : $base_path.$urls['path']);
					$urls['path']=parse_url($url,PHP_URL_PATH);
					$rel_path=strpos($urls['path'], '/')===0 ? ltrim($urls['path'],'/') : $base_path.$urls['path'];
				}else{
					$url=$this->normalizePath($url);
					$rel_path=$urls['path'];
				}
				//是否为原始静态文件
				$is_original=false;
				//获取扩展名
				$ext=strtolower(strrpos($rel_path,'.')!==false ? substr($rel_path,strrpos($rel_path,'.')+1):'');
				switch ($type){
					case 'css':
						if(substr($rel_path,-1)=='/'){
							$rel_path.=substr(md5($url),8,16).'.css';
						}else if($ext!='css'&&$ext!='ico'){
							$rel_path.='.css';
						}else{
							$is_original=true;
						}
						break;
					case 'js':
						if(substr($rel_path,-1)=='/'){
							$rel_path.=substr(md5($url),8,16).'.js';
						}else if($ext!='js'){
							$rel_path.='.js';
						}else{
							$is_original=true;
						}
						break;
					case 'img':
						if(substr($rel_path,-1)=='/'){
							$rel_path.=substr(md5($url),8,16).'.jpg';
						}else if($ext==''){
							$rel_path.='.jpg';
						}else{
							$is_original=true;
						}
						break;
				}
				
				if(isset($urls['query']) && !$is_original){
					$tmpUrl=($upos=strpos($url, '?')) ? substr($url,0,$upos) : $url;
					$rel_path=pathinfo($rel_path,PATHINFO_DIRNAME).'/'.pathinfo(substr($tmpUrl,strrpos($tmpUrl,'/')),PATHINFO_FILENAME).'.'.pathinfo($rel_path,PATHINFO_EXTENSION);
				}
				
				$newpath=$this->staticPath.($domain?$domain.'/':'').ltrim($rel_path,'/');
				$filename=$this->rootPath.$newpath;
				$this->log('start:get "'.$url."\"\r\n",$type);
				if(is_file($filename) || $this->sendRequest($url,$filename)){
					$replaces[]=$newpath;
					$this->log('success:save "'.$url.'" to "'.$newpath."\"\r\n",$type);
					if($type=='css'){
						$this->getStaticFile(file_get_contents($filename),$this->getPathInfo($url,'base'),'other');
					}
				}else{
					$this->log('failed:save "'.$url."\"\r\n",$type);
					unset($searches[$k]);
				}
			}else{
				unset($searches[$k]);
			}
		}
		return str_replace($searches, $replaces, $contents);
	}
	
	/**
	 * 发送请求
	 * @param string $url
	 * @param string $savepath
	 * @param array $header
	 */
	private function sendRequest($url,$data=null){
		//设置请求header
		$header=[
			'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 10_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/56.0.2924.75 Mobile/14E5239e Safari/602.1',
		];
		return get_remote_file($url,$data,$header);
	}
	
	/**
	 * 格式化路径
	 * @param string $url
	 * @param string $serilize
	 * @return mixed|string
	 */
	private function formatPath($url,$serilize=false){
		$rootPath=parse_url($this->url,PHP_URL_PATH);
		$path=parse_url($url,PHP_URL_PATH);
		$query=parse_url($url,PHP_URL_QUERY);
		
		if(empty($path)||$path=='/'){
			$path='/index.html';
		}else{
			$path=rtrim($path,'/');//  index.html
			$dpos=strpos($path,'.',strrpos($path, '/')+1);
			if($dpos!==false){
				$path=substr($path,0,$dpos).'.html';
			}else{
				$path.='/index.html';
			}
		}
		
		if($query){
			$path=substr($path,0,strrpos($path,'.')).'-'.str_replace(array('=','&'), '-', $query).'.html';
		}
		if(strpos($path, $rootPath)===0){
			$path=ltrim(substr($path,strlen($rootPath)),'/');
		}
		return $serilize ? str_replace('/','_',ltrim($path,'/')) : $path;
	}
	
	/**
	 * 获取路径相关信息
	 * @param string $url
	 * @param string $type
	 * @return string
	 */
	private function getPathInfo($url,$type=null){
		$rootPath=substr($url,0,strpos($url,'/',strpos($url,'://')+3)+1);
		if($type=='root'){
			return $rootPath;
		}
		$path=parse_url($url,PHP_URL_PATH);
		$base_path=$rootPath.substr($path,1,strrpos($path,'/'));
		if($type=='base'){
			return $base_path;
		}
	}
	
	/**
	 * 规范化路径
	 * @param string $path
	 * @return string
	 */
	private function normalizePath($path){
		$parts=array();
		$root='';
		if(strpos($path,'://')){
			$root=substr($path,0,strpos($path,'/',strpos($path, '://')+3)+1);
			$path=substr($path,strpos($path,'/',strpos($path, '://')+3)+1);
		}
		$path=str_replace('\\', '/', $path);
		$path=preg_replace('/\/+/', '/', $path);
		$segments=explode('/', $path);
		$test='';
		foreach($segments as $segment){
			if($segment != '.'){
				$test=array_pop($parts);
				if(is_null($test))
					$parts[]=$segment;
					else if($segment == '..'){
						if($test == '..')
							$parts[]=$test;
							
							if($test == '..' || $test == '')
								$parts[]=$segment;
					}else{
						$parts[]=$test;
						$parts[]=$segment;
					}
			}
		}
		return $root.($root?ltrim(implode('/', $parts),'./'):implode('/', $parts));
	}
	
	private function log($msg='',$type=''){
		static $count=0;
		$startPos=strpos($this->url, '://')+3;
		$filename=str_replace('/','_',substr($this->url, $startPos,strrpos($this->url, '/')-$startPos)).'.log';
		!$count && is_file($filename) && unlink($filename);
		$count+=file_put_contents($filename, $msg,FILE_APPEND);
		if(defined('APP_DEBUG') && APP_DEBUG){echo $msg;}
	}
}

error_reporting(E_ERROR | E_PARSE);
$webSpider=new WebSpider();
$webSpider->start();

