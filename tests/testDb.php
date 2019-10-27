<?php
include dirname(__FILE__).'/../kernel/base.php';

class Test1{
    public function test(){
        var_dump(
            M('user', '^book_')
                ->index('token')
                ->result(array($this, 'result', 12, 14))
                ->limit(3)
                ->select()
        );
    }
    
    private function result($data, $type, $type2){
        $data['openid']=md5($data['openid']).'_'.$type.'/'.$type2;
        return $data;
    }
}

$test1=new Test1();
$test1->test();

