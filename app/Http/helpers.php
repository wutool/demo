<?php

use App\Util\Util;
use App\Util\Consume;

if (!function_exists('apiRender')) {
    function apiRender($data = [])
    {
        $result = array(
            "status" => 0,
            "message" => Util::getError(OK),
            "consume" => Consume::getTime(),
            "time" => Util::getTime(false),
        );

        if (!empty($data)) {
            $result['md5'] = md5(json_encode($data));
            $result['data'] = $data;
        }

        return response(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Server' => 'nginx/1.2.3',
            ]);
    }
}

if (!function_exists('apiRenderError')) {
    function apiRenderError($code, $message)
    {
        $result = array(
            "status" => $code,
            "message" => $message,
            "consume" => Consume::getTime(),
            "time" => Util::getTime(false),
        );

        if (!$result['status']) {
            $result['status'] = ERROR_SYS_UNKNOWN;
            $result['message'] = Util::getError(ERROR_SYS_UNKNOWN);
        }

        return response(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Server' => 'nginx/1.2.3',
            ]);
    }
}

if (!function_exists('public_path')) {
    function public_path($path = null)
    {
        return rtrim(app()->basePath('public/' . $path), '/');
    }
}

if (!function_exists('camelize')) {
    /**
     * 下划线转驼峰
     * 思路:
     * step1.原字符串转小写,原字符串中的分隔符用空格替换,在字符串开头加上分隔符
     * step2.将字符串中每个单词的首字母转换为大写,再去空格,去字符串首部附加的分隔符.
     */
    function camelize($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }
}

if (!function_exists('uncamelize')) {
    /**
     * 驼峰命名转下划线命名
     * 思路:
     * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     */
    function uncamelize($camelCaps, $separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }
}

if (!function_exists('openrestyWritelist')) {
    function openrestyWritelist($filterAdmin = true)
    {
        $uri = [];
        $r = app('router')->getRoutes();
        foreach ($r as $item) {
            $isCheckadmin = false;
            $action = json_encode($item['action']);
            $action = json_decode($action, true);
            if (array_key_exists('middleware', $action)) {
                if (in_array('api.checkadmin', $action['middleware'])) {
                    $isCheckadmin = true;
                }
            }

            if ($filterAdmin) {
                // api
                if (!$isCheckadmin) {
                    if ($item['method'] = 'POST') {
                        $uri[] = ltrim($item['uri'], '/');
                    }
                }
            } else {
                // api + admin
                $uri[] = ltrim($item['uri'], '/');
            }

        }
        unset($item);
        $uri = array_filter($uri);

        return $uri;
    }
}
