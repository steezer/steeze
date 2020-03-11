<?php
namespace Library;

/**
 * ZIP压缩/解压类
 * 
 * @package Library
 */
class Zip{
	private $ctrl_dir=array();
	private $datasec=array();
	var $old_offset=0;
	var $eof_ctrl_dir="\x50\x4b\x05\x06\x00\x00\x00\x00";

	private function visitFile($path){
		$fileList=array();
		$path=str_replace('\\', '/', $path);
		$fdir=dir($path);
		while(($file=$fdir->read()) !== false){
			if($file == '.' || $file == '..'){
				continue;
			}
			$pathSub=preg_replace("*/{2,}*", '/', $path . '/' . $file); // 替换多个反斜杠
			$fileList[]=is_dir($pathSub) ? $pathSub . '/' : $pathSub;
			if(is_dir($pathSub)){
				$fileList=array_merge($fileList, $this->visitFile($pathSub));
			}
		}
		$fdir->close();
		return $fileList;
	}

	private function unix2DosTime($unixtime=0){
		$timearray=($unixtime == 0) ? getdate() : getdate($unixtime);
		if($timearray['year'] < 1980){
			$timearray['year']=1980;
			$timearray['mon']=1;
			$timearray['mday']=1;
			$timearray['hours']=0;
			$timearray['minutes']=0;
			$timearray['seconds']=0;
		}
		return (($timearray['year'] - 1980) << 25) | 
                ($timearray['mon'] << 21) | 
                ($timearray['mday'] << 16) | 
                ($timearray['hours'] << 11) | 
                ($timearray['minutes'] << 5) | 
                ($timearray['seconds'] >> 1);
	}

	private function addFile($data,$filename,$time=0){
		$filename=str_replace('\\', '/', $filename);
		$dtime=dechex($this->unix2DosTime($time));
		$hexdtime='\x' . $dtime[6] . $dtime[7] . '\x' . $dtime[4] . $dtime[5] . '\x' . $dtime[2] . $dtime[3] . '\x' . $dtime[0] . $dtime[1];
		eval('$hexdtime = "' . $hexdtime . '";');
		
		$fr="\x50\x4b\x03\x04";
		$fr.="\x14\x00";
		$fr.="\x00\x00";
		$fr.="\x08\x00";
		$fr.=$hexdtime;
		$unc_len=strlen($data);
		$crc=crc32($data);
		$zdata=gzcompress($data);
		$c_len=strlen($zdata);
		$zdata=substr(substr($zdata, 0, strlen($zdata) - 4), 2);
		$fr.=pack('V', $crc);
		$fr.=pack('V', $c_len);
		$fr.=pack('V', $unc_len);
		$fr.=pack('v', strlen($filename));
		$fr.=pack('v', 0);
		$fr.=$filename;
		
		$fr.=$zdata;
		
		$fr.=pack('V', $crc);
		$fr.=pack('V', $c_len);
		$fr.=pack('V', $unc_len);
		
		$this->datasec[]=$fr;
		$new_offset=strlen(implode('', $this->datasec));
		
		$cdrec="\x50\x4b\x01\x02";
		$cdrec.="\x00\x00";
		$cdrec.="\x14\x00";
		$cdrec.="\x00\x00";
		$cdrec.="\x08\x00";
		$cdrec.=$hexdtime;
		$cdrec.=pack('V', $crc);
		$cdrec.=pack('V', $c_len);
		$cdrec.=pack('V', $unc_len);
		$cdrec.=pack('v', strlen($filename));
		$cdrec.=pack('v', 0);
		$cdrec.=pack('v', 0);
		$cdrec.=pack('v', 0);
		$cdrec.=pack('v', 0);
		$cdrec.=pack('V', 32);
		
		$cdrec.=pack('V', $this->old_offset);
		$this->old_offset=$new_offset;
		$cdrec.=$filename;
		$this->ctrl_dir[]=$cdrec;
	}

	private function file(){
		$data=implode('', $this->datasec);
		$ctrldir=implode('', $this->ctrl_dir);
		return $data . 
                $ctrldir . 
                $this->eof_ctrl_dir . 
                pack('v', sizeof($this->ctrl_dir)) . 
                pack('v', sizeof($this->ctrl_dir)) . 
                pack('V', strlen($ctrldir)) . 
                pack('V', strlen($data)) . "\x00\x00";
	}

	private function readCentralFileHeaders($fp){
		$binary_data=fread($fp, 46);
		$header=unpack('vchkid/vid/vversion/vversion_extracted/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', $binary_data);
		$header['filename']=($header['filename_len'] != 0) ? fread($fp, $header['filename_len']) : '';
		$header['extra']=($header['extra_len'] != 0) ? fread($fp, $header['extra_len']) : '';
		$header['comment']=($header['comment_len'] != 0) ? fread($fp, $header['comment_len']) : '';
		if($header['mdate'] && $header['mtime']){
			$hour=($header['mtime'] & 0xF800) >> 11;
			$minute=($header['mtime'] & 0x07E0) >> 5;
			$seconde=($header['mtime'] & 0x001F) * 2;
			$year=(($header['mdate'] & 0xFE00) >> 9) + 1980;
			$month=($header['mdate'] & 0x01E0) >> 5;
			$day=$header['mdate'] & 0x001F;
			$header['mtime']=mktime($hour, $minute, $seconde, $month, $day, $year);
		}else{
			$header['mtime']=time();
		}
		$header['stored_filename']=$header['filename'];
		$header['status']='ok';
		if(substr($header['filename'], -1) == '/'){
			$header['external']=0x41FF0010;
		} // 判断是否文件夹
		return $header;
	}

	private function readFileHeader($fp){
		$binary_data=fread($fp, 30);
		$data=unpack('vchk/vid/vversion/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len', $binary_data);
		
		$header['filename']=fread($fp, $data['filename_len']);
		$header['extra']=($data['extra_len'] != 0) ? fread($fp, $data['extra_len']) : '';
		$header['compression']=$data['compression'];
		$header['size']=$data['size'];
		$header['compressed_size']=$data['compressed_size'];
		$header['crc']=$data['crc'];
		$header['flag']=$data['flag'];
		$header['mdate']=$data['mdate'];
		$header['mtime']=$data['mtime'];
		
		if($header['mdate'] && $header['mtime']){
			$hour=($header['mtime'] & 0xF800) >> 11;
			$minute=($header['mtime'] & 0x07E0) >> 5;
			$seconde=($header['mtime'] & 0x001F) * 2;
			$year=(($header['mdate'] & 0xFE00) >> 9) + 1980;
			$month=($header['mdate'] & 0x01E0) >> 5;
			$day=$header['mdate'] & 0x001F;
			$header['mtime']=mktime($hour, $minute, $seconde, $month, $day, $year);
		}else{
			$header['mtime']=time();
		}
		
		$header['stored_filename']=$header['filename'];
		$header['status']='ok';
		return $header;
	}

	private function extractFile($header, $dist, $fp){
		$header=$this->readfileheader($fp);
		
		if(substr($dist, -1) != '/'){
			$dist.='/';
		}
		if(!is_dir($dist)){
			@mkdir($dist, 0777);
		}
		
		$pth=explode('/', dirname($header['filename']));
		$pthss='';
		for($i=0; isset($pth[$i]); $i++){
			if(!$pth[$i]){
				continue;
			}
			$pthss.=$pth[$i] . '/';
			if(!is_dir($dist . $pthss)){
				@mkdir($dist . $pthss, 0777);
			}
		}
		
		if(!(isset($header['external']) && $header['external'] == 0x41FF0010) && !(isset($header['external']) && $header['external'] == 16)){
			if($header['compression'] == 0){
				$fp=@fopen($dist . $header['filename'], 'wb');
				if(!$fp){
					return (-1);
				}
				$size=$header['compressed_size'];
				
				while($size != 0){
					$read_size=($size < 2048 ? $size : 2048);
					$buffer=fread($fp, $read_size);
					$binary_data=pack('a' . $read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size-=$read_size;
				}
				fclose($fp);
				touch($dist . $header['filename'], $header['mtime']);
			}else{
				$fp=@fopen($dist . $header['filename'] . '.gz', 'wb');
				if(!$fp){
					return (-1);
				}
				$binary_data=pack('va1a1Va1a1', 0x8b1f, Chr($header['compression']), Chr(0x00), time(), Chr(0x00), Chr(3));
				fwrite($fp, $binary_data, 10);
				$size=$header['compressed_size'];
				while($size != 0){
					$read_size=($size < 1024 ? $size : 1024);
					$buffer=fread($fp, $read_size);
					$binary_data=pack('a' . $read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size-=$read_size;
				}
				
				$binary_data=pack('VV', $header['crc'], $header['size']);
				fwrite($fp, $binary_data, 8);
				fclose($fp);
				$gzp=@gzopen($dist . $header['filename'] . '.gz', 'rb') or die('Cette archive est compress!');
				
				if(!$gzp){
					return (-2);
				}
				$fp=@fopen($dist . $header['filename'], 'wb');
				if(!$fp){
					return (-1);
				}
				$size=$header['size'];
				
				while($size != 0){
					$read_size=($size < 2048 ? $size : 2048);
					$buffer=gzread($gzp, $read_size);
					$binary_data=pack('a' . $read_size, $buffer);
					@fwrite($fp, $binary_data, $read_size);
					$size-=$read_size;
				}
				fclose($fp);
				gzclose($gzp);
				touch($dist . $header['filename'], $header['mtime']);
				@unlink($dist . $header['filename'] . '.gz');
			}
		}
		return true;
	}
    
    
	private function readCentralDir($fp, $filename){
		$filesize=filesize($filename);
		fseek($fp, $filesize - 22);
		$EofCentralDirSignature=unpack('Vsignature', fread($fp, 4));
		if($EofCentralDirSignature['signature'] != 0x06054b50){
			$maxLength=65535 + 22;
			$maxLength > $filesize && $maxLength=$filesize;
			fseek($fp, $filesize - $maxLength);
			$searchPos=ftell($fp);
			while($searchPos < $filesize){
				fseek($fp, $searchPos);
				$sigData=unpack('Vsignature', fread($fp, 4));
				if($sigData['signature'] == 0x06054b50){
					break;
				}
				$searchPos++;
			}
		}
		$data=unpack('vdisk/vdisk_start/vdisk_entries/ventries/Vsize/Voffset/vcomment_size', fread($fp, 18));
		$centd['comment']=($data['comment_size'] != 0) ? fread($fp, $data['comment_size']) : ''; // 注释
		$centd['entries']=$data['entries'];
		$centd['disk_entries']=$data['disk_entries'];
		$centd['offset']=$data['offset'];
		$centd['disk_start']=$data['disk_start'];
		$centd['size']=$data['size'];
		$centd['disk']=$data['disk'];
		return $centd;
	}
	
	/**
     * 压缩到服务器
     *
     * @param string $dirname
     * @param string $distFilename
     */
	public function zipToFile($dirname, $distFilename){
		if(@!function_exists('gzcompress')){
			return;
		}
		ob_end_clean();
		$filelist=$this->visitFile($dirname);
		if(count($filelist) == 0){
			return;
		}
		
		$this->ctrl_dir=array();
		$this->datasec=array();
		
		foreach($filelist as $file){
			if(!is_file($file) || !is_file($file)){
				continue;
			}
			$fd=fopen($file, 'rb');
			$content=@fread($fd, filesize($file));
			fclose($fd);
			$file=substr($file, strlen($dirname));
			if(substr($file, 0, 1) == '\\' || substr($file, 0, 1) == '/'){
				$file=substr($file, 1);
			}
			$this->addFile($content, $file);
		}
		$out=$this->file();
		
		$distDir=dirname($distFilename);
		if(!is_dir($distDir)){
			@mkdir($distDir, 0777);
		}
		
		$fp=fopen($distFilename, 'wb');
		fwrite($fp, $out, strlen($out));
		fclose($fp);
	}
	
	/**
     * 压缩并直接下载
     *
     * @param string $dirname
     * @param string $filename
     */
	public function zipToDownload($dirname, $filename=''){
		if(@!function_exists('gzcompress')){
			return;
		}
		ob_end_clean();
		$filelist=$this->visitFile($dirname);
		if(count($filelist) == 0){
			return;
		}
		
		$this->ctrl_dir=array();
		$this->datasec=array();
		foreach($filelist as $file){
			if(!is_file($file) || !is_file($file)){
				continue;
			}
			$fd=fopen($file, 'rb');
			$content=@fread($fd, filesize($file));
			fclose($fd);
			$file=substr($file, strlen($dirname));
			if(substr($file, 0, 1) == '\\' || substr($file, 0, 1) == '/'){
				$file=substr($file, 1);
			}
			$this->addFile($content, $file);
		}
		
		$out=$this->file();
		$filename=$filename ? $filename : 'download' . date('YmdHis', time()) . '.zip';
		header('Content-Encoding: none');
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment ; filename=' . $filename);
		header('Pragma: no-cache');
		header('Expires: 0');
		print($out);
	}
	
	/**
     * 解压文件
     *
     * @param string $filename
     * @param string $dist
     * @param array $index
     */
	public function unZip($filename, $dist, $index=array(-1)){
		if(!$filename || !is_file($filename)){
			return false;
		}
		$ok=0;
		$fp=fopen($filename, 'rb');
		if(!$fp){
			return (-1);
		}
		
		$cdir=$this->readCentralDir($fp, $filename);
		$pos_entry=$cdir['offset'];
		
		if(!is_array($index)){
			$index=array($index);
		}
		for($i=0, $max=count($index); $i < $max; $i++){
			if(intval($index[$i]) != $index[$i] || $index[$i] > $cdir['entries']){
				return (-1);
			}
		}
		
		for($i=0; $i < $cdir['entries']; $i++){
			fseek($fp, $pos_entry);
			$header=$this->readCentralFileHeaders($fp);
			$header['index']=$i;
			$pos_entry=ftell($fp);
			rewind($fp);
			fseek($fp, $header['offset']);
			if(in_array('-1', $index) || in_array($i, $index)){
				$stat[$header['filename']]=$this->extractFile($header, $dist, $fp);
			}
		}
		fclose($fp);
		return $stat;
	}
	
	/**
     * 获取被压缩文件的信息
     *
     * @param string $filename
     * @return array
     */
	public function getZipInnerFilesInfo($filename){
		if(!$filename || !is_file($filename)){
			return false;
		}
		$fp=fopen($filename, 'rb');
		if(!$fp){
			return (0);
		}
		$centd=$this->readCentralDir($fp, $filename);
		
		rewind($fp);
		fseek($fp, $centd['offset']);
		$ret=array();
		for($i=0; $i < $centd['entries']; $i++){
			$header=$this->readCentralFileHeaders($fp);
			$header['index']=$i;
			$info=array(
					'filename' => $header['filename'], // 文件名
					'stored_filename' => $header['stored_filename'], // 压缩后文件名
					'size' => $header['size'], // 大小
					'compressed_size' => $header['compressed_size'], // 压缩后大小
					'crc' => strtoupper(dechex($header['crc'])), // CRC32
					'mtime' => date("Y-m-d H:i:s", $header['mtime']), // 文件修改时间
					'comment' => $header['comment'], // 注释
					'folder' => ($header['external'] == 0x41FF0010 || $header['external'] == 16) ? 1 : 0, // 是否为文件夹
					'index' => $header['index'], // 文件索引
					'status' => $header['status'] // 状态
				); 

			$ret[]=$info;
			unset($header);
		}
		fclose($fp);
		return $ret;
	}
    
	/**
     * 获取压缩文件的注释
     *
     * @param string $filename
     * @return string
     */
	public function getZipComment($filename){
		if(!$filename || !is_file($filename)){
			return false;
		}
		$fp=fopen($filename, 'rb');
		if(!$fp){
			return false;
		}
		$centd=$this->readCentralDir($fp, $filename);
		fclose($fp);
		return $centd['comment'];
	}
    
}
