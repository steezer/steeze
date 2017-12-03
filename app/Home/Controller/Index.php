<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;

class Index extends Controller{
	
	public function __construct(){
	}
	
	public function index(){
		var_dump(__FILE__);
	}
	
	public function test2(){
		var_dump(__FUNCTION__);
	}
	
	public function test(Model $user){
		$this->assign('user',$user);
		$this->display();
	}
	
	public function _show(){
		$this->display();
	}
}
