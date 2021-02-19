# auto_change_dnspod
如果触发封堵则修改dnspod解析

## composer install

## 配置
`config.php`
```
    'uid'             => 'xxxxxxxx',//dnspod uid
    'token'           => 'xxxxxxxxxx',//dnspod token
    'domain'          => 'xxx.site',//需要监听站点
    'available_ip_pool' => [
        '172.23.8.106',
        '172.23.8.108',
        '172.23.18.18',
        '172.23.7.207',
    ],//备用ip，当某个ip被封堵，则自动切换，同时你需要预先在你的服务器上挂载多个ip
    'redis'=>[
        'host'=>'172.23.8.73',
        'port'=>'6379',
        'password'=>'',
        'timeout'=>'10',
        'select'=>'3',
    ],//需要redis支持
    'disable_duration'=>30,//ip封禁时间 单位分钟，在封堵时间内不去尝试，有些机房有最小封堵时长
```

## php index.php  自动检测
## php auto_change.php 自动切换 可加入 crontab
## php clear.php 清除redis列表
  
