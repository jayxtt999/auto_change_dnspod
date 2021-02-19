<?php

require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

try {
    $redisConfig = $config['redis'];
    $redisHandle = new \Redis;
    $redisHandle->connect($redisConfig['host'], $redisConfig['port'], $redisConfig['timeout']);
    if ('' != $redisConfig['password']) {
        $redisHandle->auth($redisConfig['password']);
    }
    if ('' != $redisConfig['select']) {
        $redisHandle->select($redisConfig['select']);
    }
} catch (\Exception $e) {
    consoleLog($e->getMessage());
    die();

}

$redisHandle->delete('record_list');

echo 'clear success';