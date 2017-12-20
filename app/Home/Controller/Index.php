<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;

class Index extends Controller{
	
	public function index(){
		return var_export(__FUNCTION__,true);
	}
	
	public function test(Model $user){
		$this->assign('user',$user);
		$this->display();
	}
	
	public function _show(){
		$this->display();
	}
}
