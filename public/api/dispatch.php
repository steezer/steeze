<?php
include '../stwms/base.php';
$actions=array('action','plugin','redirect');
$default_params=array();
foreach ($_GET as $k=>$v){
	if(!in_array($k, $actions)){
		$default_params[$k]=$v;
	}
}

if(isset($_GET['action'])){
	$action=trim($_GET['action']);
	if(strpos($action,'://')!==false || strpos($action, '/')===0){
		//直接URL，可能是外部的URL
		header('Location:'.U($action,$default_params,true));
		exit(0);
	}else{
		//直接为URL的方法名称,例如:Index/show?id=10
		$URL=U($action,$default_params,true);
	}
}elseif(isset($_GET['plugin'])){
	$para=strpos($_GET['plugin'], '?');
	$c=!$para?$_GET['plugin']:substr($_GET['plugin'],0,$para);
	$a='';
	if($p=strpos($c, '/')){
		$a=substr($c,$p+1);
		$c=substr($c,0,$p);
	}
	
	$para=$para?substr($_GET['plugin'],$para+1):'';
	parse_str($para,$para);
	$para=array_merge($para,$default_params);
	$URL=U(':'.$c.'/'.$a,$para,true);
}else if(isset($_GET['redirect'])){
	$redirect=trim($_GET['redirect']);
	if(strpos($redirect,'://')!==false || strpos($redirect, '/')===0){
		//直接URL，可能是外部的URL
		header('Location:'.U($redirect,$default_params,true));
		exit(0);
	}else{ //解码后的URL
		$URL=U(sys_crypt($redirect,0),$default_params,true);
	}
}
if($URL){
	header('Location:'.str_replace($_SERVER['PHP_SELF'],'/index.php',$URL));
}