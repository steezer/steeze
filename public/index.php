<?php
define('ROOT_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('APP_DEBUG_LEVEL', E_ALL ^ E_STRICT);
include dirname(__FILE__).'/../kernel/base.php';
!defined('TEMPLATE_REPARSE') && define('TEMPLATE_REPARSE',APP_DEBUG); //模版缓存
Loader::app();
