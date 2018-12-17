<?php
namespace App\Home\Controller\Member;
use Library\Controller;
use Library\Model;

class Index extends Controller{
	
	public function hello(){
		$this->display();
	}
	
	public function _info(){
		$this->assign('info',['id'=>12,'name'=>'spring']);
		$this->display();
	}
	
}
