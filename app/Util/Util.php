<?php

namespace App\Util;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class Util
{
    public static function isValidClient($userid, $deviceid, $platform, $network, $version, $rand, $netspeed, $time, $guid)
    {
        $params = array(
            "userid" => $userid,
            "deviceid" => $deviceid,
            "platform" => $platform,
            "network" => $network,
            "version" => $version,
            "rand" => $rand,
            "netspeed" => $netspeed,
            "time" => $time
        );

        ksort($params);

        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v;
        }
        unset($k, $v);

        $apiAccessConfig = Context::getConfig("accessConfig");

        return $guid == md5($str . $apiAccessConfig["client"]["token"]) ? true : false;
    }

    public static function isValidServer($rand, $time, $guid)
    {
        $params = array(
            "rand" => $rand,
            "time" => $time
        );

        foreach ($params as $key => $value) {
            $params[$key] = rawurldecode(urlencode($value));
        }

        $api_access_config = Context::getConfig('accessConfig');
        if ($guid != md5(implode("_", $params) . $api_access_config["server"]["token"])) {
            return false;
        }

        if (isset($api_access_config["server"]["hosts"])) {
            $allowed = false;
            foreach ($api_access_config["server"]["hosts"] as $host) {
                if (strpos(Util::getIP(), $host) !== false) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        Log::debug(json_encode([
            'isValidServer' => Util::getIP(),
        ]));

        return true;
    }

    public static function isValidAdmin($rand, $time, $guid)
    {
        $params = array(
            "rand" => $rand,
            "time" => $time
        );

        foreach ($params as $key => $value) {
            $params[$key] = rawurldecode(urlencode($value));
        }

        $api_access_config = Context::getConfig('accessConfig');
        if ($guid != md5(implode("_", $params) . $api_access_config["admin"]["token"])) {
            return false;
        }

        if (isset($api_access_config["admin"]["hosts"])) {
            $allowed = false;
            foreach ($api_access_config["admin"]["hosts"] as $host) {
                if (strpos(Util::getIP(), $host) !== false) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        Log::debug(json_encode([
            'isValidAdmin' => Util::getIP(),
        ]));

        return true;
    }

    public static function isValidCdn($rand, $time, $guid)
    {
        $params = array(
            "rand" => $rand,
            "time" => $time
        );

        foreach ($params as $key => $value) {
            $params[$key] = rawurldecode(urlencode($value));
        }

        $api_access_config = Context::getConfig('accessConfig');
        if ($guid != md5(implode("_", $params) . $api_access_config["h5"]["token"])) {
            return false;
        }

        if (isset($api_access_config["h5"]["hosts"])) {
            $allowed = false;
            foreach ($api_access_config["h5"]["hosts"] as $host) {
                if (strpos(Util::getIP(), $host) !== false) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        return true;
    }

    public static function checkFlood($guid)
    {
        $key = "flood_" . $guid;
        $cache = Redis::connection('common');

        if (null === $cache->get($key)) {
            $cache->set($key, 1, 'EX', 7200);
            return true;
        }

        return false;
    }

    public static function getTime($cache = true)
    {
        static $time;

        if ($cache) {
            if (!$time) {
                $time = isset($_SERVER['REQUEST_TIME']) && !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
            }
        } else {
            $time = time();
        }

        return $time;
    }

    public static function getError($errno)
    {
        $errorMessage = config('errorlist.' . $errno);
        return $errorMessage ? $errorMessage : '';
    }

    /**
     * 获取请求真实ip
     *
     * 使用知道创宇云安全后如何获取访客真实IP
     * 假设访问过程如下：
     * 网民IP — 代理IP1—代理IP2—云安全节点—网站服务器。
     * 其中直接和云安全节点相连的IP不可伪造，非连接的IP可伪造。如果网民访问网站没有使用VPN代理，则除过REMOTE_ADDR外得到的IP地址是一样的，均是网民电脑IP。
     * 在网站服务器上看到的IP值如下（PHP写法，如$_SERVER[‘REMOTE_ADDR’]）：
     * REMOTE_ADDR：节点IP
     * HTTP_X_FORWARDED_FOR：网民IP,代理IP1,代理IP2
     * HTTP_X_REAL_FORWARDED_FOR：网民IP
     * HTTP_X_CONNECTING_IP：代理IP2，一般推荐使用这个，即直接和云安全节点相连接的IP，因为没有直接和云安全节点连接的IP是可伪造的
     */
    public static function getIP()
    {
        $ip = '';

        if (array_key_exists('HTTP_X_CONNECTING_IP', $_SERVER) && $_SERVER["HTTP_X_CONNECTING_IP"]) {
            $ip = $_SERVER["HTTP_X_CONNECTING_IP"];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $ip = getenv("REMOTE_ADDR");
        } elseif (array_key_exists('REMOTE_ADDR', $_SERVER) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } elseif (array_key_exists('HTTP_CLIENT_IP', $_SERVER) && $_SERVER['HTTP_CLIENT_IP']) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $ip = getenv("HTTP_CLIENT_IP");
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        return $ip;
    }

    public static function isMobile($mobile)
    {
        return 0 != preg_match("/(^1[3456789]{1}\d{9}$)|(^\+1[2-9]\d{2}[2-9](?!11)\d{6}$)/", $mobile);
    }


    public static function timeEcho($time)
    {
        $time = abs($time);
        $hour = floor($time / 60 / 60);
        $time -= $hour * 60 * 60;
        $minute = floor($time / 60);
        $time -= $minute * 60;
        $second = $time;
        $elapse = '';

        $unitArr = ['小时' => 'hour', '分钟' => 'minute', '秒' => 'second'];

        foreach ($unitArr as $cn => $u) {
            if ($$u > 0) {
                $elapse .= $$u . $cn;
            }
        }
        unset($cn, $u);

        return $elapse;
    }

    public static function strRandom($length = 16)
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }
}
