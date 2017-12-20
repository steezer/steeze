<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;

class User extends Controller{
	
	public function index(){
		return $_SERVER;
	}
	
	
	public function _info(Model $user){
		$this->assign('info',$user);
		$this->display();
	}
}
