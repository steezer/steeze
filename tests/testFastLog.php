<?php
include dirname(__FILE__).'/../../kernel/base.php';

C([
    'max_logfile_size' => 0.01, // 单个日志文件最大大小（单位：M）
    'max_logfile_num' => 3, // 单类日志文件最大数量，为0则不限
]);

for ($i=0; $i <1 ; $i++) {
    $str=str_repeat('123123123123123123123123123',1000);
    fastlog($str, true, 'test.log');
}
