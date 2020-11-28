<?php


namespace App\Http\Services;

use App\Models\ForbiddenDevice;
use Illuminate\Support\Facades\Redis;

class ForbiddenDeviceService
{
    public function addForbidden($deviceid, $reason)
    {
        $forbidden = new ForbiddenDevice();
        if (!$forbidden->existForbidden($deviceid)) {
            $forbidden->addForbidden($deviceid, $reason);
        }

        $key = 'forbidden_device';
        $cache = $this->getCache();
        $cache->sadd($key, $deviceid);

        return true;
    }

    public function unForbidden($deviceid)
    {
        $forbidden = new ForbiddenDevice();
        $forbidden->unForbidden($deviceid);

        $key = 'forbidden_device';
        $cache = $this->getCache();
        $cache->srem($key, $deviceid);

        return true;
    }

    private function getCache()
    {
        return $cache = Redis::connection('common');
    }

    public static function isForbidden($deviceid)
    {
        $key = 'forbidden_device';
        $cache = Redis::connection('common');

        return (boolean)$cache->sismember($key, $deviceid);
    }
}
