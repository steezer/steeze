<?php
namespace App\Home\Controller;
use Library\Controller;
use Library\Model;
use Library\Request;
use Library\Response;

/**
 * 常规开发范例
 */
class Index extends Controller{
    
    //模板变量赋值
	public function hello(){
		$this->assign('name','liming');
		$this->assign('year',23);
		$this->assign('company',['year'=>12,'name'=>['s'=>'liuyun']]);
		$this->display();
	}
	
    //数据库表的参数绑定
	public function test(Model $user){
		$this->assign('user',$user);
		$this->display();
	}
	
    //模板内部控制器方法调用
	public function _show(Model $user){
        $this->assign('user', $user);
		$this->display();
	}
}
