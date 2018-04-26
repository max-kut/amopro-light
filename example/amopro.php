<?php
if(__FILE__ == $_SERVER['SCRIPT_FILENAME']){
	die('скрипт только для подключения в другие скрипты');
}

require_once __DIR__ . '/AmoPRO.phar';

$amoproConfig = require_once __DIR__ . '/config.php';

$amopro = new \AmoPRO\AmoPRO($amoproConfig);
$amoproOrder = new \AmoPRO\Order();
