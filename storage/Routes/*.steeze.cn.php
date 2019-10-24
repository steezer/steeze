<?php
use Library\Model;
use Library\Container;

return [
  //   '/'=> function(){
		// return 'Under construction...';
  //   },
    
    '/container' => function(){
        //非swoole模式下运行的容器对象实例都绑定到了应用对象
        $container=Container::getInstance();
        return get_class($container);  //输出： Library\Application
    },
    
    '/info/{user|d?}' => function(Model $user){
        $this->assign('user', $user);
        $this->display('/User/info');
    },
    
    '/session' => function(\Library\Request $request, \Library\Response $response){
        session('[start]');
        $str='<pre>';
        $str.=var_export(
            [
                'header' => $request->header(),
                'session_id'=>session('[id]'),
                'session_name'=>session_name(),
                'all_status'=>[
                    'PHP_SESSION_DISABLED'=>PHP_SESSION_DISABLED, 
                    'PHP_SESSION_NONE'=>PHP_SESSION_NONE, 
                    'PHP_SESSION_ACTIVE'=>PHP_SESSION_ACTIVE
                ],
                'current_status'=>session_status()
            ], true
        );
        $str.='</pre>';
        $response->cookie(session_name(), session('[id]'));
        return $str;
    },
    '/hello'=> 'Index/hello',
    '/member/index/hello'=> 'Member/Index/hello',
    'convert' => [
        '/{c}/{a}#page={page|d}'=>'{c}/{a}',
        '/{c}/{a}/{user|d}#a={id|d?}'=>'{c}/{a}',
    ]
];