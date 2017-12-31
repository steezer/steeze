<?php
return [
	'default' => [
			'type'=>  C('db_type',env('db_type','mysql')),     // 数据库类型
			'host'=>  C('db_host',env('db_host','127.0.0.1')), // 服务器地址
			'name'=>  C('db_name',env('db_name','test')),          // 数据库名
			'user'=>  C('db_user',env('db_user','root')),      // 用户名
			'pwd'=>  C('db_pwd',env('db_pwd','')),          // 密码
			'port'=>  C('db_port',env('db_port','3306')),        // 端口
			'prefix'=>  C('db_prefix',env('db_prefix','')),    // 数据库表前缀
	],
	'lite' => [
			'type' => 'sqlite3',
			'dsn' => STORAGE_PATH.'Data'.DS.'lite_sqlite3.db',
			'prefix' => C('db_prefix',env('db_prefix',''))
	]
];
