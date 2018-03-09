<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;
use Library\Request;
use Library\Response;

class Index extends Controller{
	
	public function __construct(Request $request,Response $response){
		
	}
	
	//测试
	public function hello(){
		$this->assign('name','liming');
		$this->assign('year',23);
		$this->assign('company',['year'=>12,'name'=>['s'=>'liuyun']]);
		$this->display();
	}
	
	public function test(Model $user){
		$this->assign('user',$user);
		$this->display();
	}
	
	public function _show(){
		$this->display();
	}
}
