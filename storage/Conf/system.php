<?php
/*
 * 注意：本文件不支持路由常量配置
 */
return [
	// 常规设置
	'version' => '1.0.0', // 应用版本
	'charset' => 'utf-8', // 网站字符集
	'timezone' => 'Etc/GMT-8', // 网站时区（只对php 5.1以上版本有效），Etc/GMT-8 实际表示的是 GMT+8
	'lang' => 'zh-cn', // 网站语言包
	'debug' => 0, // 是否显示调试信息
	'lock_ex' => 1, // 写入缓存时是否建立文件互斥锁定（如果使用nfs建议关闭）
	'default_thumb'=>'', //默认缩略图片
	'db_conn'=>'default', //数据库默认连接配置
	
	'attachment_stat' => 1, // 附件状态使用情况统计
	'errorlog' => 0, // 是否保存错误日志到LOGS_PATH/exception.log
	'errorlog_size' => 20, // 错误日志预警大小，单位：M
	'gzip' => 0,
	'tmpl_exception_tpl' => KERNEL_PATH.'View'.DS.'exception.php', //错误显示PHP原生模版路径【不支持模版变量】
	
	//风格设置
	'default_tpl' => 'Default', // 前台模板名称，位于APP_PATH/MODULE_NAME/View/目录下
	'default_assets' => 'default', // 前台访问静态文件路径，如果以/开头相对于ASSETS_URL，如果为相对路径则相对于ASSETS_URL/app/MODULE_NAME/

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