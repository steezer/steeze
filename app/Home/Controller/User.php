<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;

class User extends Controller{
	
	public function index(){
		var_dump($_SERVER);
		//return M('user')->where('id<5')->select();
	}
	
	
	public function _info(Model $user){
		$this->assign('user',$user);
		$this->display();
	}
}
