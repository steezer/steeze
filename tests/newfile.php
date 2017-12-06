<?php
include dirname(__FILE__).'/../kernel/base.php';

var_dump(M('case','lite')->field('id,title')->limit(5)->select());
