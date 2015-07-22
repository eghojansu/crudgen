<?php

require 'vendor/autoload.php';

$moe = moe\Base::instance();
$dir = 'app/config/';
$ext = '.ini';
foreach ([
    'system',
    'routes',
    'app',
] as $config)
    $moe->config($dir.$config.$ext);

$moe->run();
