<?php
//此配置文件只能在全局配置
return [
	'default' => [
		'/'=> 'auth&convert>home/index@index',
		'auth&convert' => [
			'/{c}/{a}'=>'home/{c}@{a}',
			'/{c}/{a}/{user|d}'=>'home/{c}@{a}',
		]
	]
];