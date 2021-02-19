<?php
/**
 * Created by PhpStorm.
 * User: xietaotao
 * Date: 2021/2/1
 * Time: 11:50
 */
require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

error_reporting(E_ERROR);
ini_set("display_errors", 1);

$uid        = $config['uid'];//uid
$token      = $config['token'];//token
$domain     = $config['domain'];//域名
$recordName = '@';//name
$recordType = 'A';//type

$checkPort           = 22;//全局检测端口 TODO linux windows
$defaultTimeOut      = 5;//连接超时时间
$retry               = 3;//重试次数 //TODO 调用第三方接口检测可用性
$retryTimeOutModulus = 2;//重试系数 超时时间 = 配置超时时间+ (重试次数 * 重试系数) 单位秒


//key为md5后的ip  [md5(ip)=>ip]
$availableIpPool = $config['available_ip_pool'];
$disableDuration = $config['disable_duration'];

foreach ($availableIpPool as $key => $item) {
    $hashKey                   = md5($item);
    $availableIpPool[$hashKey] = $item;
    unset($availableIpPool[$key]);
}

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


$ipList = $redisHandle->get('record_list');//待检测ip列表   $record:id => $record:ip
if (!$ipList) {
    $dpClass   = new \PhpDnspod\Dnspod($uid, $token);
    $recordRsp = $dpClass->getRecordList($domain);
    $records   = $recordRsp['records'];
    $ipList    = [];//待检测ip列表   $record:id => $record:ip
    foreach ($records as $record) {
        //可用的A记录
        if ($record['enabled'] == '1' && $record['type'] == 'A') {
            $ipList[(string)$record['id']] = $record['value'];
        }
    }
    $redisHandle->set('record_list', json_encode($ipList), 3600);
} else {
    $ipList = json_decode($ipList, true);
}


if ($ipList) {

    foreach ($ipList as $recordId => $ip) {

        try {

            consoleLog('[' . $ip . ']开始');
            //是否在封禁ip列表，存在且未到解封时间，则跳过
            $ipScore = $redisHandle->zScore('disable_ip_list', $ip);
            if ($ipScore) {
                //$ipScore 实际为解封时间，未到达则跳过
                if (time() < $ipScore) {
                    consoleLog('[' . $ip . ']在封禁列表，跳过');
                    continue;
                }
            }


            $ipMd5Key = md5($ip);

            $retryNum = $retry;
            $checkRes = false;
            for ($i = 0; $i < $retryNum; $i++) {

                consoleLog('[' . $ip . ']开始第' . ($i + 1) . "次检测");
                $timeOut = $defaultTimeOut + ($i * $retryTimeOutModulus);
                $fp      = fsockopen($ip, $checkPort, $errNo, $errStr, $timeOut);
                if (!$fp) {
                    consoleLog('[' . $ip . ":" . $checkPort . "]异常， 错误信息 errno：" . $errNo . " error：" . mb_convert_encoding($errStr, 'UTF-8', 'GBK'));
                } else {
                    consoleLog('[' . $ip . ":" . $checkPort . "] ok");
                    $checkRes = true;
                    break;
                }
            }
            if ($checkRes) {
                consoleLog("[" . $ip . "]正常");
                $redisHandle->zRem('disable_ip_list', $ip);
                continue;
            } else {
                //加入封禁ip集合
                $enableTime = strtotime('+' . $disableDuration . ' minute');
                $redisHandle->zAdd('disable_ip_list', $enableTime, $ip);

            }
            consoleLog("[" . $ip . "]尝试更换解析");
            //获取可用IP
            $replace = false;

            foreach ($availableIpPool as $ipKey => $ipItem) {

                //是否为当前ip
                if ($ipMd5Key == $ipKey) {
                    continue;
                }

                //是否在封禁ip列表，存在且未到解封时间，则跳过
                $ipScore = $redisHandle->zScore('disable_ip_list', $ipItem);
                if ($ipScore) {
                    //$ipScore 实际为解封时间，未到达则跳过
                    if (time() < $ipScore) {
                        continue;
                    }
                }

                consoleLog("[" . $ipItem . "]尝试更换解析,获取新IP[" . $ipItem . "]");
                $fp = fsockopen($ipItem, $checkPort, $errNo, $errStr, $defaultTimeOut);
                if (!$fp) {
                    consoleLog('[' . $ipItem . ":" . $checkPort . "]异常， 错误信息 errno：" . $errNo . " error：" . mb_convert_encoding($errStr, 'UTF-8', 'GBK'));

                    //加入封禁ip集合
                    $enableTime = strtotime('+' . $disableDuration . ' minute');
                    $redisHandle->zAdd('disable_ip_list', $enableTime, $ipItem);


                    continue;

                } else {
                    $redisHandle->zRem('disable_ip_list', $ip);

                    consoleLog('[' . $ipItem . ":" . $checkPort . "] ok");
                }

                consoleLog('尝试请求DnsPod修改解析');
                $res = $dpClass->recordModify($domain, $recordId, $recordName, $ipItem, $recordType);
                if (isset($res['status']['code']) && $res['status']['code'] == '1') {
                    consoleLog('请求DnsPod修改解析成功,原IP[' . $ip . ']，新IP[' . $ipItem . ']');
                    $replace = true;
                    break;
                } else {
                    consoleLog('请求DnsPod修改解析失败' . (isset($res['status']['message']) ? $res['status']['message'] : '未知错误'));
                }

            }

            if (!$replace) {
                consoleLog("[" . $ip . "]尝试更换解析失败！");
            }

        } catch (\Exception $e) {
            consoleLog($e->getMessage());

        }

    }

}

consoleLog(date('Y-m-d H:i:s') . " end...\r\n\r\n");


function consoleLog($message)
{
    //echo $message . "\r\n"; //linux shell
    echo mb_convert_encoding($message, 'GBK', 'UTF-8') . "\r\n"; //windows cmd
    file_put_contents('logs/index_console_' . date('Ymd'), $message . "\r\n", FILE_APPEND);

}