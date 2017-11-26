<?php
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
	'tmpl_exception_file' => KERNEL_PATH.'Tpl'.DS.'exception.php', //错误显示模版（不支持模版标签）
	
	//风格设置
	'default_tpl' => 'Default', // 前台模板名称，位于APP_PATH/MODULE_NAME/View/目录下
	'default_resx' => 'default', // 前台访问静态文件路径
		// 如果以/开头相对于STATIC_URL，如果为相对路径则相对于STATIC_URL/app/MODULE_NAME/

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
	                   
	// 安全配置
	'auth_key' => 'b7fa4c8fdeb29b39'
];