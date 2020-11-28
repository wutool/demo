<?php


namespace App\Util;

use App\Exceptions\ApiException;

class Interceptor
{
    public static function ensureNull($result, $errno, $args = array())
    {
        if (!is_null($result)) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }

    public static function ensureNotNull($result, $errno, $args = array())
    {
        if (is_null($result)) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }

    public static function ensureNotEmpty($result, $errno, $args = array())
    {
        if (empty($result)) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }

    public static function ensureEmpty($result, $errno, $args = array())
    {
        if (!empty($result)) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }

    public static function ensureNotFalse($result, $errno, $args = array())
    {
        if ($result === false) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }

    public static function ensureFalse($result, $errno, $args = array())
    {
        if ($result !== false) {
            throw new ApiException($errno, $args);
        }

        return $result;
    }
}