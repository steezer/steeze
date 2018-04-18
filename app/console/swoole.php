<?php
include dirname(__FILE__).'/../../kernel/base.php';
!defined('TEMPLATE_REPARSE') && define('TEMPLATE_REPARSE',APP_DEBUG); //模版缓存
/**
 * swoole服务器客户端工具
 */

!class_exists('swoole_http_server',false) && 
	exit("Swoole server extension is not install,see: https://www.swoole.com/\n");
$http = new swoole_http_server("0.0.0.0", 9501);
$http->on('request', function ($request, $response) {
	$filename=ROOT_PATH.ltrim($request->server['request_uri']);
	if(is_file($filename)){
		$contentType=C('mimetype.'.fileext($filename),'application/octet-stream');
		$response->header('Accept-Ranges','bytes');
		$response->header('Content-Type',$contentType); // 网页字符编码
		$response->sendfile($filename);
	}else{
		Loader::app($request, $response);
	}
});
	
$http->start();