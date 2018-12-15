<?php
//define('DEFAULT_APP_NAME',''); //如果不使用模块取消注释
include dirname(__FILE__).'/../../kernel/base.php';
!defined('TEMPLATE_REPARSE') && define('TEMPLATE_REPARSE',APP_DEBUG); //模版缓存

/**
 * swoole服务器客户端工具
 */

//Swoole对象检查
!class_exists('swoole_http_server',false) && 
	exit("Swoole server extension is not install,see: https://www.swoole.com/\n");

//启动对象
$http = new swoole_http_server("0.0.0.0", 9501);
$http->set([
    'document_root'=> ROOT_PATH, //静态文件路径
    'enable_static_handler'=>true, //启用静态文件解析
]);
//响应请求
$http->on('request', function ($request, $response) {
	Loader::app($request, $response);
});
$http->start();
