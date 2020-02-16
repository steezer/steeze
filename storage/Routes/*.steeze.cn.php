<?php
use Library\Model;
use Library\Container;

return [
    '/'=> function(){
		return 'Under construction...';
    },
    
    '/container' => function(){
        //非swoole模式下运行的容器对象实例都绑定到了应用对象
        $container=Container::getInstance();
        return get_class($container);  //输出： Library\Application
    },
    
    '/info/{user|d?}' => function(Model $user){
        $this->assign('user', $user);
        $this->display('/User/info');
    },
    '/test'=> 'Index/test',
    'convert' => [
        '/{c}/{a}'=>'{c}/{a}',
        '/{c}/{a}#page={page|d}'=>'{c}/{a}',
        '/{c}/{a}/{user|d}#a={id|d?}'=>'{c}/{a}',
        '/{m}/{c}/{a}'=>'{m}/{c}/{a}',
    ],
    '/**'=> 'Index/hello'
];