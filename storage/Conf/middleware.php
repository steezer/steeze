<?php
//此配置文件只能在全局配置
return [
	'auth'=>'App\\Home\\Middleware\\Authorize',
	'convert'=>'App\\Home\\Middleware\\CharsetConvert',
];