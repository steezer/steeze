<?php
include dirname(__FILE__).'/../kernel/base.php';

$client=new \Library\Client();
echo $client->getOnlineIp();
