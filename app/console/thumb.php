#!/usr/bin/env php
<?php
include dirname(__FILE__).'/../../kernel/base.php';

/**
 * 图片批量处理工具
 */

use Library\CommandColor;

class BatchImageResizer{
	private $image=null; //图片处理对象
	private $filename=''; //当前处理文件路径，可以是目录或文件
	private $maxWidth=920;  //默认最大宽度
	private $maxHeight=0; //默认最大高度
	private $cutType=0; //默认剪裁类型
	private $supportCutTypes=[0=>'no',1=>'shrink',2=>'zoom']; //支持的剪裁类型，0:不剪裁，1:缩小剪裁，2:放大剪裁
	private $types=['jpg']; //图片类型
	private $supportTypes=['jpg','png','jpeg','gif']; //支持的图片类型
	private $saveDir=''; //另存为图片的路径，默认为原路径
	private $outputType=''; //输出图片类型，默认为输入图片类型
	private $count=0;
	
	public function __construct(){
		$this->image=new Library\Image();
	}
	
	public function start(){
		if($this->init()){
			$info='width:'.($this->maxWidth ? $this->maxWidth : 'auto').', ';
			$info.='height:'.($this->maxHeight ? $this->maxHeight : 'auto').', ';
			$info.='cut:'.$this->supportCutTypes[$this->cutType].', ';
			$info.='types:'.implode(',', $this->types);
			if(!empty($this->saveDir)){
				$info.='save to:'.$this->saveDir.', ';
			}
			if(!empty($this->outputType)){
				$info.='output type:'.$this->outputType;
			}
			echo CommandColor::get($info,'red')."\n";
			if(is_dir($this->filename)){
				echo "start... in \"".$this->filename."\"\n";
				$this->thumb($this->filename);
			}else{
				echo "start... for \"".$this->filename."\"\n";
				$this->doThumb($this->filename);
			}
			
			echo CommandColor::get('Total resized: '.$this->count,'green')."\n";
		}
	}
	
	/**
	 * 功能：扫描目录下的图片文件
	 * @param $dir string 路径
	 */
	public function thumb($dir){
		$dir=$this->dirPath($dir);
		if(!is_dir($dir)){
			return false;
		}
		$lists=glob($dir . '*');
		$sum=0;
		$total=0;
		foreach($lists as $v){
			if(is_dir($v)){
				$this->thumb($v);
			}else{
				$total++;
				if($this->checkFile($v) && $this->doThumb($v)){
					$sum++;
				}
			}
		}
		echo 'Resized '.$sum.'/'.$total.' files in '.$dir.''."\n";
	}
	
	/*
	 * 功能：检查文件是否需要处理
	 * @param $filename string 文件路径
	 *
	 * */
	private function checkFile($filename){
		$ext=fileext($filename,'');
		return $ext && in_array($ext, $this->types) ? true : false;
	}
	
	
	/*
	 * 功能：转换图片文件
	 * @param $filename string 文件路径
	 * 
	 * */
	private function doThumb($filename){
		if(!empty($this->saveDir)){ // /fdfd/fdf/a.jpg
			$save_filename=ltrim(substr($filename,strlen($this->filename)),DS);
			if(empty($save_filename)){ //是文件的情况下，将文件另存为指定目录
				$save_filename=basename($filename);
			}
			$output_filenme=$this->saveDir.DS.$save_filename;
		}else{
			$output_filenme=$filename;
		}
		
		if(!empty($this->outputType) && ($pos=strrpos($output_filenme, '.'))){
			$output_filenme=substr($output_filenme,0,$pos+1).$this->outputType;
		}
		
		$result=$this->image->thumbImg(
					$filename,
					$output_filenme,
					$this->maxWidth,
					$this->maxHeight,
					$this->cutType
				);
		if($result===0){
			echo CommandColor::get('Failed: '.$filename,'red')."\n";
		}
		if($result){
			$this->count++;
			return true;
		}
		return false;
	}
	
	/**
	 * 功能：转化 \ 为 /
	 * @param $path string 路径
	 * @return string	路径
	 */
	private function dirPath($path=''){
		$path=!empty($path) ? str_replace('\\', '/', $path) : '.';
		if(substr($path, -1) != '/'){
			$path=$path . '/';
		}
		return $path;
	}
	
	/**
	 * 初始化
	 * */
	private function init(){
		$shortopts  = 'd:w:h:c:t:s:o:';
		$longopts = ['dir:','width:','height:','cut:','type:','save:','output:'];
		if($options = getopt($shortopts,$longopts)){
			$filename=isset($options['d']) ? $options['d'] : $options['dir'];
			if(is_dir($filename) || is_file($filename)){
				$this->filename=$filename;
				//获取宽、高和剪裁类型参数
				$arrays=['w|width|maxWidth|int','h|height|maxHeight|int','c|cut|cutType|int'];
				foreach($arrays as $v){
					$vs=explode('|', $v);
					if(isset($options[$vs[0]]) || isset($options[$vs[1]])){
						$value=isset($options[$vs[0]]) ? $options[$vs[0]] : $options[$vs[1]];
						$this->{$vs[2]}=isset($vs[3]) && $vs[3]=='int' ? intval($value) : $value;
					}
				}
				
				//获取类型参数并校验
				if(isset($options['t']) || isset($options['type'])){
					$types=array_intersect($this->supportTypes,array_filter(explode(',',isset($options['t']) ? $options['t'] : $options['type'])));
					$this->types=!empty($types) ? $types : ['jpg'];
				}
				
				//获取输出类型参数并校验
				if(isset($options['o']) || isset($options['output'])){
					$output_type=strtolower(isset($options['o']) ? $options['o'] : $options['output']);
					if(in_array($output_type, $this->supportTypes)){
						$this->outputType=$output_type;
					}
				}
				
				//获取输出目录参数
				if(isset($options['s']) || isset($options['save'])){
					$save_dir=rtrim(strtolower(isset($options['s']) ? $options['s'] : $options['save']),DS);
					if(!empty($save_dir)){
						!is_dir($save_dir) && mkdir($save_dir,0777,true);
						$this->saveDir=$save_dir;
					}
				}
				
				
				//校验宽和高设置
				if($this->maxWidth==0 && $this->maxHeight==0){
					echo CommandColor::get('Width and height cannot be "0" at the same time!','red')."\n";
					return false;
				}
				
				//校验剪裁类型设置
				if(!isset($this->supportCutTypes[$this->cutType])){
					echo CommandColor::get('Unsupported cut type!','red')."\n";
					return false;
				}
				
				return true;
			}else{
				echo CommandColor::get('Directory or file "'.$filename.'" not exists!','red')."\n";
			}
		}else{
			echo $this->help();
		}
		return false;
	}
	
	/**
	 * 帮助说明
	 */
	private function help(){
		$str="
			".CommandColor::get('This tool is help you to batch resize images','green')."
			Usage: thumb [option]
			option:
				-d | --dir  The directory to be processed (".CommandColor::get('Required','red').")
				-w | --width  Maximum width pix for resize, \"0\" for auto,default: ".$this->maxWidth."
				-h | --height  Maximum height pix for resize,\"0\" for auto,default: ".$this->maxHeight."
				-s | --save  The other directory to save, default is current directory
				-c | --cut  Cut type, for 0 without cut, for 1 shrink cut, for 2 zoom cut, default: \"0\"
				-t | --type Type of image to be processed, default \"jpg\", support for \"".implode(', ', $this->supportTypes)."\"
				-o | --output The output image type, default same as input
		";
		return trim(str_replace(["\t\t\t","\t"], ['','  '], $str))."\n";
	}
}

error_reporting(E_ERROR | E_PARSE);
$resizer=new BatchImageResizer();
$resizer->start();
