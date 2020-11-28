<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SessionService
{
    const SECRET = '----------'; //密钥
    const VERSION = 1;                //版本
    const EXPIRE = 604800;        //有效期86400 * 7
    const STORAGE = true;            //是否存储

    public static function getToken($uid)
    {
        $daoUser = new User();
        $userinfo = $daoUser->getUserInfo($uid);

        $seed = substr(sprintf("%u", crc32(md5($userinfo["salt"]))), 0, 4);
        $salt = self::_getSalt($seed);
        $header = bin2hex(pack("------", self::VERSION, $uid, time() + self::EXPIRE, $seed));
        $random = self::random();
        $sign = self::_getSign(self::VERSION, $uid, $salt, time() + self::EXPIRE, $random);
        $token = $header . $random . $sign;

        if (self::STORAGE) {
            $key = self::_getSessionKey($uid);
            if (!Redis::connection('session')->set($key, $salt . $random, 'EX', self::EXPIRE)) {
                return false;
            } else {
            }
        }

        return $token;
    }

    private static function _getSalt($salt)
    {
        return substr(sha1((string)$salt), 0, 6);
    }

    private static function _getSign($version, $uid, $salt, $expire, $random)
    {
        $str = $version . $uid . $salt . $expire . $random . self::SECRET;

        return substr(md5($str), 4, 4);
    }

    public static function isLogined($token)
    {
        $token_info = self::_getTokenInfo($token);

        $userid = $token_info["header"]["userid"];
        $version = $token_info["header"]["version"];
        $expire = $token_info["header"]["expire"];
        $seed = $token_info["header"]["seed"];

        $sign = $token_info["sign"];
        $random = $token_info["random"];

        $salt = self::_getSalt($seed);

        if ($expire < time()) {
            return false;
        }

        if ($sign == self::_getSign($version, $userid, $salt, $expire, $random)) {
            if (self::STORAGE) {
                (string)$value = Redis::connection('session')->get(self::_getSessionKey($userid));
                if (($salt . $random) === $value) {
                    return true;
                } else {
                }
            }
        } else {
        }

        return false;
    }

    public static function getLoginId($token)
    {
        if (self::isLogined($token)) {
            $token_info = self::_getTokenInfo($token);

            return (int)$token_info["header"]["userid"];
        }

        return 0;
    }

    private static function random($length = 12)
    {
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

        $result = '';
        for ($p = 0; $p < $length; $p++) {
            $result .= ($p % 2) ? $chars[mt_rand(19, 23)] : $chars[mt_rand(0, 18)];
        }

        return $result;
    }

    //24位header + 12位随机 + 4位签名
    public static function _getTokenInfo($token)
    {
        $random = substr($token, -16, -4);
        $sign = substr($token, -4);
        $header = @unpack("++++++++", self::_hex2bin(substr($token, 0, -16)));

        return array("header" => $header, "random" => $random, "sign" => $sign);
    }

    private static function _hex2bin($hex)
    {
        if (function_exists("hex2bin")) {
            return hex2bin($hex);
        } else {
            return @pack("H*", $hex);
        }
    }

    private static function _getSessionKey($uid)
    {
        return sprintf("user:session:%d", $uid);
    }
}
