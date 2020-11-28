<?php


namespace App\Util;

use App\Util\Interceptor;

class Context
{
    private static $_container;

    public static function add($key, $value)
    {
        Interceptor::ensureFalse(isset(self::$_container[$key]), ERROR_SYS_CONTEXT_KEY_EXISTS, $key);

        self::$_container[$key] = $value;
        return true;
    }

    public static function set($key, $value)
    {
        self::$_container[$key] = $value;
        return true;
    }

    public static function get($key)
    {
        return self::$_container[$key];
    }

    public static function getConfig($key)
    {
        $data = config($key);
        Interceptor::ensureNotNull($data, ERROR_SYS_CONTEXT_KEY_NOT_EXISTS, $key);

        return $data;
    }
}