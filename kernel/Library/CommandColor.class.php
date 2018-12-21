<?php
namespace Library;

/**
 * 命令行颜色控制类
 * 
 * @package Library
 */
class CommandColor {
	// 前景颜色
	private $foreground_colors = array(
			'black'=>'0;30',
			'dark_gray'=>'1;30',
			'blue'=>'0;34',
			'light_blue'=>'1;34',
			'green'=>'0;32',
			'light_green'=>'1;32',
			'cyan'=>'0;36',
			'light_cyan'=>'1;36',
			'red'=>'0;31',
			'light_red'=>'1;31',
			'purple'=>'0;35',
			'light_purple'=>'1;35',
			'brown'=>'0;33',
			'yellow'=>'1;33',
			'light_gray'=>'0;37',
			'white'=>'1;37',
	);
	// 背景颜色
	private $background_colors = array(
			'black'=>'40',
			'red'=>'41',
			'green'=>'42',
			'yellow'=>'43',
			'blue'=>'44',
			'magenta'=>'45',
			'cyan'=>'45',
			'light_gray'=>'47',
	);
	
	/**
	 * 返回带颜色的字体和背景字符串
	 * @param $string string 原始字符串
	 * @param $foreground_color string 字符串文字颜色
	 * @param $background_color string 字符串背景颜色
	 * @return string
	 */
	public function getColoredString($string, $foreground_color = null, $background_color = null) {
		if(stripos(PHP_OS, 'WIN')===0){
			return $string;
		}
		$colored_string = "";
		if (isset($this->foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
		}
		if (isset($this->background_colors[$background_color])) {
			$colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
		}
		$colored_string .=  $string . "\033[0m";
		
		return $colored_string;
	}
	
	/**
	 * 返回所有字体颜色名称
	 */
	public function getForegroundColors() {
		return array_keys($this->foreground_colors);
	}
	
	/**
	 * 返回所有背景颜色名称
	 */
	public function getBackgroundColors() {
		return array_keys($this->background_colors);
	}
	
	/**
	 * 获取字符串颜色
	 * @see CommandColor#getColoredString
	 * */
	public static function get($string, $foreground_color = null, $background_color = null){
		$command=make('\Library\CommandColor');
		return $command->getColoredString($string, $foreground_color, $background_color);
	}
}