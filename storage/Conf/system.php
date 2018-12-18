<?php
/*
 * 注意：此文件为全局配置文件，应用模块中会对相应配置覆盖，
 *      在匹配路由中应用模块之前，使用的是全局配置
 */
return [
    // 常规设置
    'version' => '1.0.0', // 应用版本
    'charset' => 'utf-8', // 网站字符集
    'timezone' => 'Etc/GMT-8', // 网站时区，Etc/GMT-8 实际表示的是 GMT+8
    'lang' => 'zh-cn', // 网站语言包
    'debug' => 0, // 是否显示调试信息
    'lock_ex' => 1, // 写入缓存时是否建立文件互斥锁定（如果使用nfs建议关闭）
    'default_thumb'=>'', //默认缩略图片
    'db_conn'=>'default', //数据库默认连接配置
    'attachment_stat' => 1, // 附件状态使用情况统计
    'errorlog' => 0, // 是否保存错误日志到LOGS_PATH/exception.log
    'split_logfile' => true, // 是否分割日志文件，如果为true，超出单个日子文件max_logfile_size大小则自动分割处理
    'max_logfile_size' => 20, // 单个日志文件最大大小（单位：M）
    'max_logfile_num' => 0, // 单类日志文件最大数量，为0则不限数量
    'show_system_trace' => false, //是否显示系统trace记录
    'trace_max_record' => 100, //最大trace记录数量

    'gzip' => 0, //是否开启GZIP压缩输出
    'tmpl_exception_tpl' => KERNEL_PATH.'View'.DS.'exception.php', //错误显示PHP原生模板路径【不支持模板变量】

    //风格设置
    'default_theme' => 'Default', // 前台模板名称，位于APP_PATH/MODULE_NAME/View/目录下

    /**
    * 如果以/开头相对于ASSETS_URL
    * 如果为相对路径则相对于ASSETS_URL/app/MODULE_NAME/
    * 如果以http:// 开头则资源文件使用此配置为地址前缀
    */
    'default_assets' => 'default', //前台访问静态文件路径

    //默认路由参数
    'var_module'=>'m', //控制器变量名称
    'var_controller'=>'c', //控制器变量名称
    'var_action'=>'a', //处理方法变量名称
    'default_c'=>'index', //默认控制器
    'default_a'=>'index', //默认方法

    // Session配置
    'session_prefix' => 'sts_',
    'var_session_id' => 'PHPSESSID',

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