<?php


namespace App\Http\Services;

use App\Http\Services\UserService;
use App\Models\Forbidden;
use Illuminate\Support\Facades\Redis;
use App\Util\Interceptor;

class ForbiddenService
{
    /**
     * 封禁
     *
     * @param $relateid
     * @param $expire
     * @param string $reason
     * @param int $liveid
     * @return mixed
     */
    public static function addForbidden($relateid, $expire, $reason = "", $liveid = 0)
    {
        $daoForbidden = new Forbidden();
        $forbiddenInfo = $daoForbidden->getForbidden($relateid);

        Interceptor::ensureEmpty($forbiddenInfo, ERROR_USER_ERR_ISBLACK, $relateid);

        $daoForbidden->addForbidden($relateid, time() + $expire, $reason);

        $userService = new UserService();
        $userService->loginOut($relateid);

        $cache = Redis::connection('forbidden');

        return $cache->setex(self::getForbiddenKey($relateid), $expire, $relateid);
    }

    public static function unForbidden($relateid)
    {
        $daoForbidden = new Forbidden();
        $daoForbidden->unForbidden($relateid);

        $cache = Redis::connection('forbidden');

        return $cache->del(self::getForbiddenKey($relateid));
    }

    public static function isForbidden($userid)
    {
        $cache = Redis::connection('forbidden');

        try {
            $key = self::getForbiddenKey($userid);
            $result = $cache->ttl($key) > 0;
        } catch (Exception $e) {
            Logger::log('forbidden_err', 'isforbidden_user::failure', array(
                'key' => $key,
                "errmsg" => $e->getMessage()
            ));

            $daoForbidden = new Forbidden();
            $forbiddenInfo = $daoForbidden->getForbidden($userid);
            $result = $forbiddenInfo && time() < $forbiddenInfo['expire'];
        }

        return $result;
    }

    public static function isForbiddenUsers($uids)
    {
        if (!$uids) {
            return array();
        }

        $forbiddenInfo = array();
        $keys = array();

        $uids = is_string($uids) ? array($uids) : $uids;

        foreach ($uids as $uid) {
            $keys[] = self::getForbiddenKey($uid);
        }

        $cache = Redis::connection('forbidden');
        try {
            $results = $cache->mget($keys);

            foreach ($results as $row) {
                if ($row) {
                    $forbiddenInfo[] = $row;
                }
            }
        } catch (Exception $e) {
            Logger::log('forbidden_err', 'isForbiddenUsers:failure', array(
                'key' => implode(",", $keys),
                "errmsg" => $e->getMessage()
            ));

            $daoForbidden = new Forbidden();
            $forbidden_infos = $daoForbidden->getForbiddenLists($uids);

            foreach ($forbidden_infos as $info) {
                if (time() < $info['expire']) {
                    $forbiddenInfo[] = $info['relateid'];
                }
            }
        }

        return $forbiddenInfo;
    }

    public static function getForbiddenKey($uid)
    {
        return sprintf(config('rediskey.FORBIDDEN_USER_KEY'), $uid);
    }
}
