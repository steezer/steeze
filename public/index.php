<?php
define('TEMPLATE_REPARSE',true); //不使用模版缓存
define('APP_DEBUG', true);
include dirname(__FILE__).'/../kernel/base.php';
Loader::app();
