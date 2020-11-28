<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\Redis;
use App\Models\Counter;
use App\Jobs\CounterChangeJob;

/**
 * 计数器服务
 *
 * Class CounterService
 * @package App\Http\Services
 */
class CounterService
{
    const COUNTER_TYPE_PASSPORT_FEEDS_NUM = "user_feeds_num";

    const COUNTER_TYPE_FOLLOWERS = "followers"; # 粉丝数
    const COUNTER_TYPE_FOLLOWINGS = "followings"; # 关注数

    const COUNTER_TYPE_LIVE_CHATS = "live_chats";   # 发言次数
    const COUNTER_TYPE_LIVE_WATCHES = "live_watches";// 直播观看次数
    const COUNTER_TYPE_LIVE_TICKET = "live_ticket";//直播间收入数
    const COUNTER_TYPE_LIVE_PLAN_TICKET = "live_plan_ticket";//直播间回合收入

    public static function increase($type, $relateid, $number = 1)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key);

        $total = $redis->incrBy($key, $number);

        if ($total !== false) {
            # 异步写入数据库
            $micortime = round(microtime(true) * 1000);
            dispatch(new CounterChangeJob($type, $relateid, $total, $micortime));
        }

        return $total;
    }

    public static function decrease($type, $relateid, $number = 1)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key);

        $total = $redis->incrBy($key, -$number);

        if ($total !== false) {
            $micortime = round(microtime(true) * 1000);
            dispatch(new CounterChangeJob($type, $relateid, $total, $micortime));
        }

        return $total;
    }

    public static function set($type, $relateid, $number)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key);

        $result = $redis->set($key, $number);

        if ($result == 'OK') {
            $micortime = round(microtime(true) * 1000);
            dispatch(new CounterChangeJob($type, $relateid, $number, $micortime));
        }

        return $number;
    }

    public static function setex($type, $relateid, $number, $expire)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key);

        $result = $redis->setex($key, $expire, $number);

        if ($result == 'OK') {
            $micortime = round(microtime(true) * 1000);
            dispatch(new CounterChangeJob($type, $relateid, $number, $micortime));
        }

        return $number;
    }

    public static function expire($type, $relateid, $expire)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key);

        return $redis->expire($key, $expire);
    }

    public static function get($type, $relateid)
    {
        $key = self::getKey($type, $relateid);
        $redis = self::getRedis($key, true);
        $counter = $redis->get($key);
        return $counter !== false ? (int)$counter : 0;
    }

    public static function gets($type, array $relateids)
    {
        foreach ($relateids as $relateid) {
            $key = self::getKey($type, $relateid);
            $redis = self::getRedis($key, true);

            $counter = $redis->get($key);
            $result[$relateid] = $counter !== false ? (int)$counter : 0;
        }

        return $result;
    }

    public static function mixed($types, array $relateids)
    {
        $result = array();
        foreach ($types as $type) {
            foreach ($relateids as $relateid) {
                $key = self::getKey($type, $relateid);
                $redis = self::getRedis($key);

                $counter = $redis->get($key);
                $result[$relateid][$type] = $counter !== false ? (int)$counter : 0;
            }
        }

        return $result;
    }

    public static function sync2db($product, $type, $relateid, $value, $microtime)
    {
        $dao = new Counter();
        return $dao->setCounter($product, $type, $relateid, $value, $microtime);
    }

    protected static function getKey($type, $relateid)
    {
        return 'zb' . '_' . $type . '_' . $relateid;
    }

    protected static function getRedis($key)
    {
        // todo hash到多个库
        //$hash = self::getRedisHash($key);

        return Redis::connection('counter');
    }

    protected static function getRedisHash($key)
    {
        return abs(crc32($key));
    }

    public static function getBatchCount($type, array $arrTemp)
    {
        if (empty($type) || empty($arrTemp)) {
            return false;
        }

        $arrKey = $relateids = array();
        foreach ($arrTemp as $relateid) {
            if (!empty($relateid)) {
                $key = 'zb' . '_' . $type . '_' . $relateid;
                array_push($arrKey, $key);
                array_push($relateids, $relateid);
            }
        }
        $cache = Redis::connection('counter');
        $list = $cache->mget($arrKey);

        return array_combine($relateids, $list);
    }
}
