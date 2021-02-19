<?php

return [

    'uid'             => 'xxxxxxxx',//dnspod uid
    'token'           => 'xxxxxxxxxx',//dnspod token
    'domain'          => 'xxx.site',//监听站点
    'available_ip_pool' => [
        '172.23.8.106',
        '172.23.8.108',
        '172.23.18.18',
        '172.23.7.207',
    ],//可用ip
    'redis'=>[
        'host'=>'172.23.1.100',
        'port'=>'6379',
        'password'=>'',
        'timeout'=>'10',
        'select'=>'1',
    ],
    'disable_duration'=>30,//ip封禁时间 单位分钟
];