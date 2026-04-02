<?php

declare(strict_types=1);

error_reporting(-1);

define('YII_ENABLE_ERROR_HANDLER', false);
define('YII_ENV', 'test');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/index.php';

require dirname(__DIR__) . '/vendor/autoload.php';
