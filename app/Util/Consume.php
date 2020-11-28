<?php

namespace App\Util;

class Consume
{
    private static $_start = 0;

    public static function start()
    {
        self::$_start = microtime(true);
    }

    public static function getTime()
    {
        return round((microtime(true) - self::$_start) * 1000);
    }
}