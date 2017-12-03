<?php
/*
 * 注意：本文件不支持路由常量配置
 */
return [
	// 常规设置
	'version' => '1.0.0',
	'charset' => 'utf-8', // 网站字符集
	'timezone' => 'Etc/GMT-8', // 网站时区（只对php 5.1以上版本有效），Etc/GMT-8 实际表示的是 GMT+8
	'lang' => 'zh-cn', // 网站语言包
	'debug' => 0, // 是否显示调试信息
	'lock_ex' => 1, // 写入缓存时是否建立文件互斥锁定（如果使用nfs建议关闭）
	'default_thumb'=>'', //默认缩略图片
	
	'attachment_stat' => 1, // 附件状态使用情况统计
	'errorlog' => 0, // 是否保存错误日志到LOGS_PATH/exception.log
	'errorlog_size' => 20, // 错误日志预警大小，单位：M
	'gzip' => 0,
	'tmpl_exception_tpl' => KERNEL_PATH.'Template'.DS.'exception.php', //错误显示PHP原生模版路径【不支持模版变量】
	
	//风格设置
	'default_tpl' => 'Default', // 前台模板名称，位于APP_PATH/MODULE_NAME/View/目录下
	'default_resx' => 'default', // 前台访问静态文件路径，如果以/开头相对于STATIC_URL，如果为相对路径则相对于STATIC_URL/app/MODULE_NAME/

	//默认路由参数
	'default_c'=>'index', //默认控制器
	'default_a'=>'index', //默认方法
	
	// Session配置
	'session_prefix' => 'sts_',
	'var_session_id' => 'PHPSESSID',

	// Cookie配置
	'cookie_domain' => '', // Cookie 作用域
	'cookie_path' => '', // Cookie 作用路径
	'cookie_pre' => 'stc_', // Cookie 前缀，同一域名下安装多套系统时，请修改Cookie前缀
	'cookie_ttl' => 0, // Cookie 生命周期，0 表示随浏览器进程

	// 数据库设置
	'db_type'               =>  env('db_type','mysql'),     // 数据库类型
	'db_host'               =>  env('db_host','127.0.0.1'), // 服务器地址
	'db_name'               =>  env('db_name','test'),          // 数据库名
	'db_user'               =>  env('db_user','root'),      // 用户名
	'db_pwd'                =>  env('db_pwd',''),          // 密码
	'db_port'               =>  env('db_port','3306'),        // 端口
	'db_prefix'             =>  env('db_prefix',''),    // 数据库表前缀
	'db_params'             =>  array(), // 数据库连接参数
	'db_debug'              =>  env('db_debug',true), // 数据库调试模式 开启后可以记录SQL日志
	'db_fields_cache'       =>  env('db_fields_cache',true),        // 启用字段缓存
	'db_charset'            =>  env('db_charset','utf8'),      // 数据库编码默认采用utf8
	'db_deploy_type'        =>  env('db_deploy_type',0), // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
	'db_rw_separate'        =>  env('db_rw_separate',false),       // 数据库读写是否分离 主从式有效
	'db_master_num'         =>  env('db_master_num',1), // 读写分离后 主服务器数量
	'db_slave_no'           =>  env('db_slave_no',''), // 指定从服务器序号

	//数据缓存设置
	'data_cache_time'       =>  60,      // 数据缓存有效期 0表示永久缓存
	'data_cache_compress'   =>  false,   // 数据缓存是否压缩缓存
	'data_cache_check'      =>  false,   // 数据缓存是否校验缓存
	'data_cache_prefix'     =>  '',     // 缓存前缀
	'data_cache_type'       =>  env('data_cache_type','Redis'),  // 数据缓存类型,支持:File|Memcache|Sqlite|Redis
	'data_cache_path'       =>  CACHE_PATH.'temp'.DS,// 缓存路径设置 (仅对File方式缓存有效)
	'data_cache_key'        =>  '',	// 缓存文件KEY (仅对File方式缓存有效)
	'data_cache_subdir'     =>  false,    // 使用子目录缓存 (自动根据缓存标识的哈希创建子目录)
	'data_path_level'       =>  1,        // 子目录缓存级别

	// 安全配置
	'auth_key' => env('auth_key','b7fa4c8fdeb29b39'),  // 加密密钥
];