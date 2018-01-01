<?php
namespace App\Home\Controller;
use Library\Controller;

class Demo extends Controller{
	
	// 外部访问的方法名称
	public function hello(){
		return 'Hello world!'.__FUNCTION__;
	}
	
}
