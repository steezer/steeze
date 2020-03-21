<?php
define('BASE_PATH', dirname(__DIR__).DIRECTORY_SEPARATOR);
include BASE_PATH.'kernel/base.php';
define('TEMPLATE_REPARSE', APP_DEBUG); //模版缓存
Loader::app();
