<?php

require_once 'lib/Nozbe.php';

$username = 'suin@ryus.co.jp';
$password = 'wreu7hid';

$nozbe = new Nozbe();
$nozbe->login($username, $password);

// project inbox : ccb1631e0
// context home: 76964e4db
// 74055ffac6

//var_dump($nozbe->getContextActions('ccb1631e0', true));
var_export($nozbe->getProjectActions('ccb1631e0'));