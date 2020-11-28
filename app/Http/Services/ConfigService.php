<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\Redis;
use App\Models\OldConfig;
use App\Models\Config;

class ConfigService
{
    const VERSION_CONVERT_BASE = 3;

    const VERSION_CONVERT_POWER = 10000;

    public function getConfig($name, $platform, $version)
    {
        $version = self::convertVersion($version);

        $key = "config_{$platform}_{$version}_{$name}";

        $cache = Redis::connection('common');
        $data = $cache->get($key);

        $daoConfig = new Config();
        $cached = true;

        if (!$data) {
            $data = $daoConfig->getConfig($name, $platform, $version, $version);
            $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!empty($data) && false !== $data) {
                $cache->set($key, $data, 'EX', 30);
            }

            $cached = false;
        }

        $data = json_decode($data, true);

        if (empty($data)) {
            $data = array(
                'value' => '',
                'expire' => 0
            );
        } else {
            // 返回给客户端的均为字符串，具体业务自行解析
            is_array($data['value']) && $data['value'] = json_encode($data['value']);
            $data = array(
                'value' => (string)$data['value'],
                'expire' => (int)$data['expire']
            );
        }

        $data["cached"] = $cached;

        return $data;
    }

    public function getConfigs($names, $platform, $version)
    {
        $version = self::convertVersion($version);

        $namesMd5 = md5(implode(',', $names));
        $key = "config_{$platform}_{$version}_{$namesMd5}";

        $cache = Redis::connection('common');
        $data = $cache->get($key);

        $daoConfig = new Config();
        $cached = true;

        if (!$data) {
            $configs = $daoConfig->getConfigs($names, $platform, $version, $version);
            $data = json_encode($configs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!empty($data) && false !== $data) {
                $cache->set($key, $data, 'EX', 300);
            }

            $cached = false;
        }

        $data = json_decode($data, true);

        foreach ($names as $name) {
            if (empty($data[$name])) {
                $data[$name] = array(
                    'value' => '',
                    'expire' => 0
                );
            } else {
                // 返回给客户端的均为字符串，具体业务自行解析
                is_array($data[$name]['value']) && $data[$name]['value'] = json_encode($data[$name]['value']);
                $data[$name] = array(
                    'value' => (string)$data[$name]['value'],
                    'expire' => (int)$data[$name]['expire']
                );
            }

        }
        $data["cached"] = $cached;

        return $data;
    }

    public function setConfig($name, $value, $expire, $platform, $minVersion, $maxVersion = null)
    {
        $minVersion = self::convertVersion($minVersion);
        $maxVersion = self::convertVersion($maxVersion);

        $daoConfig = new Config();

        return $daoConfig->setConfig($name, $value, $expire, $platform, $minVersion, $maxVersion);
    }

    public function delConfig($id)
    {
        $daoConfig = new Config();

        return $daoConfig->delConfig($id);
    }

    public static function convertVersion($version, $base = self::VERSION_CONVERT_BASE, $power = self::VERSION_CONVERT_POWER)
    {
        $version = explode('.', $version);
        $result = 0;
        foreach ($version as $v) {
            $result += (int)$v * pow($power, $base--);
        }

        return ceil($result);
    }
}
