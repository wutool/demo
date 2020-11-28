<?php

namespace App\Http\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use App\Models\Following;
use App\Models\Follower;
use App\Models\Followlog;
use App\Http\Services\CounterService;
use App\Http\Services\LiveSerivce;
use App\Http\Services\UserService;
use Illuminate\Support\Facades\DB;
use App\Jobs\RankingGenerateJob;

class FollowService
{
    const KEY_PREFIX = "zb_F_";

    private static function _addFollow($uid, $fids, $reason = "")
    {
        $followed = array();

        $cache = Redis::connection('follow');
        $key = FollowService::KEY_PREFIX . $uid;
        $cache->del($key);

        $daoFollowing = new Following($uid);
        $daoFollower = new Follower($uid);
        $daoFollowlog = new Followlog();

        try {
            Db::connection('passport')->beginTransaction();

            $friends = array();
            foreach ($fids as $fid) {
                $followed[$fid] = false;

                // 是否存在关注
                if ($uid == $fid || $daoFollowing->exists($fid)) {
                    continue;
                }

                // 是否存在被关注, 变成好友关系
                $isfriend = false;
                if ($daoFollower->exists($fid)) {
                    $isfriend = true;
                    $hisdao_following = new Following($fid);
                    $hisdao_following->modFollowing($uid, array(
                        "friend" => "Y"
                    ));
                }

                $daoFollowing->addFollowing($fid, $isfriend);

                $hisdao_follower = new Follower($fid);
                $hisdao_follower->addFollower($uid);
                $daoFollowlog->addFollowlog($uid, $fid, Followlog::ACTION_ADD, $reason);

                $followed[$fid] = true;
            }

            DB::connection('passport')->commit();

        } catch (Exception $e) {
            DB::connection('passport')->rollBack();

            throw new ApiException(ERROR_SYS_DB_SQL);
        }

        $cache_vals = array();
        $follows = $daoFollowing->getFollowings(0, 3000, false, true);
        foreach ($follows as $k => $v) {
            $cache_vals[$v->fid] = 1;
        }
        unset($v);

        if ($cache_vals) {
            $key = FollowService::KEY_PREFIX . $uid;
            $cache->hmset($key, $cache_vals);
            $cache->expire($key, 345600);
        }

        return $followed;
    }

    public static function addFollow($uid, $fids, $liveid)
    {
        if (!is_array($fids)) {
            $fids = array(
                $fids
            );
        }

        $liveInfo = [];
        if ($liveid) {
            $liveService = new LiveSerivce();
            $liveInfo = $liveService->getLiveInfo($liveid);
        }

        $followed = self::_addFollow($uid, $fids, $liveid ? "room" : "");

        foreach ($followed as $fid => $v) {
            if ($v) {
                // 增加粉丝计数
                CounterService::increase(CounterService::COUNTER_TYPE_FOLLOWERS, $fid);
                // 粉丝榜
                dispatch(new RankingGenerateJob('followers', 'increase', $fid, 1));

                if ($liveInfo) {
                    if ($liveInfo['uid'] == $uid) {
                        # 关注观众
                        MessageSendService::sendLiveFollowing($liveid, UserService::getUserInfo($uid), true);
                    }

                    if ($liveInfo['uid'] == $fid) {
                        # 观众关注
                        $userInfo = UserService::getUserInfo($uid);
                        if ($userInfo['level'] > 1) {
                            MessageSendService::sendLiveFollowing($liveid, $userInfo, false);
                        }
                    }
                }

            }
        }

        $totalFollowings = FollowService::countFollowings($uid, true);
        CounterService::set(CounterService::COUNTER_TYPE_FOLLOWINGS, $uid, $totalFollowings);

        return $followed;
    }

    /**
     * 取消关注
     *
     * @param $uid
     * @param $fid
     * @param string $reason
     * @return bool
     */
    private static function _cancelFollow($uid, $fid, $reason = "")
    {
        $daoFollowing = new Following($uid);
        $followingInfo = $daoFollowing->getFollowingInfo($fid);

        if (!empty($followingInfo)) {
            $cache = Redis::connection('follow');
            $key = FollowService::KEY_PREFIX . $uid;
            $cache->del($key);

            try {
                Db::connection('passport')->beginTransaction();

                $daoFollowing->delFollowing($fid);

                $dao_follower = new Follower($fid);
                $dao_follower->delFollower($uid);

                if ("Y" == $followingInfo->friend) {
                    $hisdao_following = new Following($fid);
                    $hisdao_following->modFollowing($uid, array(
                        "friend" => "N"
                    ));
                }

                $dao_followlog = new Followlog();
                $dao_followlog->addFollowlog($uid, $fid, Followlog::ACTION_CANCEL, $reason);

                DB::connection('passport')->commit();

            } catch (Exception $e) {
                DB::connection('passport')->rollBack();

                throw new ApiException(ERROR_SYS_DB_SQL);
            }

            $cache_vals = array();
            $follows = $daoFollowing->getFollowings(0, 3000, false, true);
            foreach ($follows as $k => $v) {
                $cache_vals[$v->fid] = 1;
            }
            unset($v);

            if ($cache_vals) {
                $cache->hmset($key, $cache_vals);
                $cache->expire($key, 345600);
            }

            return true;
        }

        return false;
    }

    public static function cancelFollow($uid, $fid, $reason = "")
    {
        $canceled = self::_cancelFollow($uid, $fid, $reason);

        if ($canceled) {
            $total_followings = FollowService::countFollowings($uid, true);
            # 关注人数
            CounterService::set(CounterService::COUNTER_TYPE_FOLLOWINGS, $uid, $total_followings);
            # 粉丝数-1
            $result = CounterService::decrease(CounterService::COUNTER_TYPE_FOLLOWERS, $fid);
            // 粉丝榜
            dispatch(new RankingGenerateJob('followers', 'decrease', $fid, 1));
        }

        return $canceled;
    }

    public static function setOptionNotice($uid, $fid, $notice)
    {
        $daoFollowing = new Following($uid);
        $daoFollower = new Follower($fid);

        try {
            Db::connection('passport')->beginTransaction();

            $daoFollowing->modFollowing($fid, array(
                "notice" => $notice
            ));
            $daoFollower->modFollower($uid, array(
                "notice" => $notice
            ));

            DB::connection('passport')->commit();

        } catch (Exception $e) {
            DB::connection('passport')->rollBack();

            throw new ApiException(ERROR_SYS_DB_SQL);
        }

        return true;
    }

    public static function countFollowers($uid)
    {
        $dao_follower = new Follower($uid);
        return $dao_follower->countFollowers();
    }

    public static function countFollowings($uid, $forceMaster = false)
    {
        $dao_following = new Following($uid);
        return $dao_following->countFollowings($forceMaster);
    }

    /**
     * fids 是否为 uid 的关注
     *
     * @param $uid
     * @param $fids
     * @return array
     */
    public static function isFollowed($uid, $fids)
    {
        if (!$fids) {
            return array();
        }

        if (!is_array($fids)) {
            $fids = array(
                $fids
            );
        }

        $followed = array();

        $cache = Redis::connection('follow');

        if ($cache->exists(FollowService::KEY_PREFIX . $uid)) {
            $followed_list = $cache->hmget(FollowService::KEY_PREFIX . $uid, $fids);

            foreach ($fids as $key => $fid) {
                if ($uid == $fid) {
                    $followed[$fid] = true;
                } else {
                    if ($followed_list[$key]) {
                        $followed[$fid] = true;
                    } else {
                        $followed[$fid] = false;
                    }
                }
            }

        } else {
            $dao_following = new Following($uid);
            $followed_list = $dao_following->isFollowed($fids);

            foreach ($fids as $fid) {
                if ($uid == $fid) {
                    $followed[$fid] = true;
                } else {
                    if (array_key_exists($fid, $followed_list)) {
                        $followed[$fid] = true;
                    } else {
                        $followed[$fid] = false;
                    }
                }
            }
        }

        return $followed;
    }

    /**
     * fids 是否为 uid 的粉丝
     *
     * @param $uid
     * @param $fids
     * @return array
     */
    public static function isFollower($uid, $fids)
    { /* {{{fids 是否为 uid 的粉丝 */
        if (!$fids) {
            return array();
        }

        if (!is_array($fids)) {
            $fids = array(
                $fids
            );
        }

        $follower = array();

        foreach ($fids as $fid) {
            if ($uid == $fid) {
                $follower[$fid] = true;
            } else {
                $followed = self::isFollowed($fid, $uid);
                $follower[$fid] = $followed[$uid];
            }
        }

        return $follower;
    }

    public static function isFriend($uid, $fids)
    {
        if (!$fids) {
            return array();
        }

        if (!is_array($fids)) {
            $fids = array(
                $fids
            );
        }

        $friend = array();

        $cache = Redis::connection('follow');
        if ($cache->exists(FollowService::KEY_PREFIX . $uid)) {
            $followed_list = $cache->hmget(FollowService::KEY_PREFIX . $uid, $fids);

            $followed_list = array_filter($followed_list);
            $friend_list = array();
            foreach ($followed_list as $k => $v) {
                $friend_list[$fids[$k]] = true;
            }
            unset($v);

        } else {
            $dao_following = new Following($uid);
            $friend_list = $dao_following->isFriend($fids);
        }

        foreach ($fids as $fid) {
            if ($uid == $fid) {
                $friend[$fid] = true;
            } else {
                if (array_key_exists($fid, $friend_list)) {
                    $friend[$fid] = true;
                } else {
                    $friend[$fid] = false;
                }

            }
        }

        return $friend;
    }

    public static function relation($uid, $fid)
    {/* {{{ 获取uid 和 fid 的用户关系 四种:关注,粉丝,好友,陌生人*/
        $relation = '';

        $followed = self::isFollowed($uid, $fid);//fid是否是uid的关注
        if ($followed[$fid]) {
            $relation = 'following';//关注
        }

        $follower = self::isFollower($uid, $fid);//fid是否是uid的粉丝
        if ($follower[$fid]) {
            $relation = "follower";//粉丝
        }

        $friend = self::isFriend($uid, $fid);//是否是好友
        if ($friend[$fid]) {
            $relation = 'friend';
        }

        return empty($relation) ? 'stranger' : $relation;
    }

    public static function getUserFollowings($uid, $offset, $num, $noticed_only = "N")
    {
        $dao_following = new Following($uid);
        $list = $dao_following->getFollowings($offset, $num, $noticed_only == "Y");

        $users = array();
        if ($list) {
            foreach ($list as $k => $v) {
                $users[] = array(
                    "uid" => $v->fid,
                    "notice" => "Y" == $v->notice,
                );
            }
        }

        return $users;
    }

    public static function getUserFollowers($uid, $offset, $num, $noticed_only = "N")
    {
        $dao_follower = new Follower($uid);
        $list = $dao_follower->getFollowers($offset, $num, $noticed_only == "Y");

        $users = array();
        if ($list) {
            foreach ($list as $k => $v) {
                $users[] = array(
                    "id" => $v->id,
                    "uid" => $v->fid,
                    "notice" => "Y" == $v->notice
                );
            }
        }

        return $users;
    }

    public static function getUserFriends($uid, $offset, $num)
    {
        $dao_following = new Following($uid);
        $list = $dao_following->getFriends($offset, $num);

        $users = array();
        if ($list) {
            foreach ($list as $k => $v) {
                $users[] = array(
                    "uid" => $v->fid
                );
            }
        }

        return $users;
    }
}
