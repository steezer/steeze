<?php
namespace App\Home\Controller\Member;
use Library\Controller;
use Library\Model;

class Index extends Controller{
	
	public function hello(){
		$this->display();
	}
	
	public function _info(Model $user){
		$this->assign('info',$user);
		$this->display();
	}
	
}
