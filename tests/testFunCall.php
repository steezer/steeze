<?php
namespace Library;

include dirname(__FILE__).'/../../kernel/base.php';

function testCase(Model $user){ //自动将模型对象绑定到user表
    var_dump($user->count());
}

function testCase2(Model $wxArticles){ //将wxArticles转为表名wx_articles
    var_dump($wxArticles->count());
}

function testCase3(Model $wxArticles, $name='spring', $year=23){ //支持其它参数按名称注入
    var_dump($wxArticles->count(), $name, $year);
}


$container=Container::getInstance();

//Closure的参数注入调用
$container->invoke(function(Model $user){
    var_dump($user->count());
});

//一般函数的注入
$container->invoke('\Library\testCase');
$container->invoke('\Library\testCase2');
$container->invoke('\Library\testCase3', ['name'=>'steeze', 'year'=>22]);


