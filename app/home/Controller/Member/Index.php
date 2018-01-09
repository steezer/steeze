<?php
namespace App\Home\Controller\Member;
use Library\Controller;
use Library\Model;
use Library\Request;
use Library\Response;

class Index extends Controller{
	
	public function __construct(Request $request,Response $response){
		
	}
	
	public function hello(){
		$this->display();
	}
	
	public function _info(Model $user){
		$this->assign('info',$user);
		$this->display();
	}
	
}
