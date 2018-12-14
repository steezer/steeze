<?php
include dirname(__FILE__).'/../kernel/base.php';

//从命令行访问路由示例（主要应用于API接口测试）
$app=new \Library\Application();
$config=[
    'url'=> 'http://api.steeze.cn/user/add',
    'data'=>['info'=>['name'=>'spring','gender'=>1,'year'=>23]],
    'header'=>['TOKEN'=>'12306'],
];

//添加数据
$app->start($config); 

//删除数据
$config['url']='http://api.steeze.cn/user/delete/6';
$app->start($config);

//更新数据
$config['url']='http://api.steeze.cn/user/update/7';
$config['data']=json_encode(['name'=>'spring','gender'=>1,'year'=>23]);
$app->start($config);

//查询单条数据
$config['url']='http://api.steeze.cn/user/info/6';
$app->start($config);

//查询列表数据
$config['url']='http://api.steeze.cn/user/lists?page=1';
$app->start($config);
