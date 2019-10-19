<?php
include dirname(__FILE__).'/../kernel/base.php';

var_dump(M('user', '^tally_')->getDbTables(true));
