<?php


namespace App\Http\Controllers;

use App\Http\Services\BlockedService;
use App\Http\Services\FollowService;
use App\Http\Services\CounterService;
use App\Http\Services\ForbiddenService;
use App\Http\Services\UserService;
use App\Util\Context;
use App\Util\Interceptor;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    const FOLLOWING_LIMIT = 3000;

    /**
     * 我关注的人的列表
     */
    public function getFollowings(Request $request)
    {
        $uid = Context::get("userid");
        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 20);

        Interceptor::ensureFalse($num > 200, ERROR_PARAM_INVALID_FORMAT, "num");

        $users = $fids = array();
        $isforbidden = false;
        $more = false;
        $list = FollowService::getUserFollowings($uid, $offset, 40);

        if ($list) {
            foreach ($list as $k => $v) {
                $fids[] = $v["uid"];
            }
            # 存在封禁用户
            $forbiddenUsers = ForbiddenService::isForbiddenUsers($fids);
            foreach ($list as $k => $v) {
                if (in_array($v["uid"], $forbiddenUsers)) {
                    $isforbidden = true;
                    continue;
                }

                $userInfo = UserService::getUserInfo($v["uid"]);
                if (!$userInfo) {
                    continue;
                }
                $userInfo["followed"] = true;
                $userInfo["blocked"] = false;
                $userInfo["notice"] = (boolean)$v["notice"];
                $users[] = $userInfo;

                if (count($users) >= $num) {
                    break;
                }
            }

            $offset = $offset + $k + 1;
            if (count($list) > $num) {
                $more = true;
            }
        }

        $isRemindForbidden = $isforbidden;

        return apiRender([
            "users" => $users,
            "offset" => $offset,
            "more" => $more,
            "is_remind_forbidden" => $isRemindForbidden
        ]);
    }

    /**
     * 我的粉丝列表
     */
    public function getFollowers(Request $request)
    {
        $uid = Context::get("userid");
        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 20);

        Interceptor::ensureFalse($num > 200, ERROR_PARAM_INVALID_FORMAT, "num");

        $users = $fids = array();
        $isforbidden = false;
        $more = false;
        $list = FollowService::getUserFollowers($uid, $offset, 40);

        if ($list) {
            foreach ($list as $k => $v) {
                $fids[] = $v["uid"];
            }

            $forbiddenUsers = ForbiddenService::isForbiddenUsers($fids);

            $followed = FollowService::isFollowed($uid, $fids);

            foreach ($list as $k => $v) {
                if (in_array($v["uid"], $forbiddenUsers)) {
                    $isforbidden = true;
                    continue;
                }

                $user_info = UserService::getUserInfo($v["uid"]);
                if (!$user_info) {
                    continue;
                }

                $user_info["followed"] = (boolean)$followed[$v["uid"]];
                $users[] = $user_info;

                if (count($users) >= $num) {
                    break;
                }
            }

            $offset = $offset + $k + 1;
            if (count($list) > $num) {
                $more = true;
            }
        }

        $counter = CounterService::get(CounterService::COUNTER_TYPE_FOLLOWERS, $uid);

        $is_remind_forbidden = $isforbidden || $counter >= 200;

        return apiRender(array(
            "users" => $users,
            "offset" => $offset,
            "more" => $more,
            "is_remind_forbidden" => $is_remind_forbidden
        ));
    }

    /**
     * 好友列表
     */
    public function getFriends(Request $request)
    {
        $uid = Context::get("userid");
        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 20);

        Interceptor::ensureFalse($num > 3000, ERROR_PARAM_INVALID_FORMAT, "num");

        $users = $fids = array();
        $more = $isforbidden = false;

        $list = FollowService::getUserFriends($uid, $offset, $num);

        if ($list) {
            foreach ($list as $k => $v) {
                $fids[] = $v["uid"];
            }

            $forbidden_users = ForbiddenService::isForbiddenUsers($fids);

            foreach ($list as $k => $v) {
                $user_info = UserService::getUserInfo($v["uid"]);
                if (!$user_info || in_array($user_info["uid"], $forbidden_users)) {
                    $isforbidden = true;
                    continue;
                }

                $users[] = $user_info;
            }

            $offset = $offset + $k + 1;
            $more = $k == ($num - 1);
        }

        $is_remind_forbidden = $isforbidden;

        return apiRender(array(
            "users" => $users,
            "offset" => $offset,
            "more" => $more,
            "is_remind_forbidden" => $is_remind_forbidden
        ));
    }

    /**
     * 是否关注
     */
    public function isFollowed(Request $request)
    {
        $uid = Context::get("userid");
        $fids = trim($request->post("fids"), ",");

        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");
        Interceptor::ensureNotFalse(preg_match("/^(\d+,?)+$/", $fids) != 0, ERROR_PARAM_INVALID_FORMAT, "fids($fids)");

        if (strcmp($uid, $fids) == 0) {
            $followed = array(
                $uid => true
            );
        } else {
            $followed = FollowService::isFollowed($uid, explode(",", $fids));
        }

        return apiRender(array(
            "users" => $followed
        ));
    }

    /**
     * 是否好友
     */
    public function isFriend(Request $request)
    {
        $uid = Context::get("userid");
        $fids = trim($request->post("fids"), ",");

        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");
        Interceptor::ensureNotFalse(preg_match('/^(\d+,?)+$/', $fids) != 0, ERROR_PARAM_INVALID_FORMAT, "fids($fids)");

        $friends = FollowService::isFriend($uid, explode(",", $fids));

        return apiRender(array(
            "friends" => $friends
        ));
    }

    /**
     * 用户关注的人的列表
     */
    public function getUserFollowings(Request $request)
    {
        $loginid = Context::get("userid");

        $uid = (int)$request->post("uid", 0);
        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");

        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 20);

        Interceptor::ensureFalse($num > 100, ERROR_PARAM_INVALID_FORMAT, "num");

        $users = $fids = array();
        $isforbidden = false;
        $more = false;
        $list = FollowService::getUserFollowings($uid, $offset, 40);

        if ($list) {
            foreach ($list as $k => $v) {
                $fids[] = $v["uid"];
            }
            # 存在封禁用户
            $forbiddenUsers = ForbiddenService::isForbiddenUsers($fids);

            $followed = FollowService::isFollowed($loginid, $fids);

            foreach ($list as $k => $v) {
                if (in_array($v["uid"], $forbiddenUsers)) {
                    $isforbidden = true;
                    continue;
                }

                $userInfo = UserService::getUserInfo($v["uid"]);
                if (!$userInfo) {
                    continue;
                }

                $userInfo["followed"] = (boolean)$followed[$v["uid"]];
                $users[] = $userInfo;

                if (count($users) >= $num) {
                    break;
                }
            }

            $offset = $offset + $k + 1;
            if (count($list) > $num) {
                $more = true;
            }
        }

        $is_remind_forbidden = $isforbidden;

        return apiRender(array(
            "users" => $users,
            "offset" => $offset,
            "more" => $more,
            "is_remind_forbidden" => $is_remind_forbidden
        ));
    }

    public function getUserFollowers(Request $request)
    {
        $loginid = Context::get("userid");
        $uid = (int)$request->post("uid", 0);
        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");

        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 20);

        Interceptor::ensureFalse($num > 200, ERROR_PARAM_INVALID_FORMAT, "num");

        $users = $fids = array();
        $isforbidden = false;
        $more = false;
        $list = FollowService::getUserFollowers($uid, $offset, 40);

        if ($list) {
            foreach ($list as $k => $v) {
                $fids[] = $v["uid"];
            }

            $forbiddenUsers = ForbiddenService::isForbiddenUsers($fids);

            $followed = FollowService::isFollowed($loginid, $fids);

            foreach ($list as $k => $v) {
                if (in_array($v["uid"], $forbiddenUsers)) {
                    $isforbidden = true;
                    continue;
                }

                $user_info = UserService::getUserInfo($v["uid"]);
                if (!$user_info) {
                    continue;
                }

                $user_info["followed"] = (boolean)$followed[$v["uid"]];
                $users[] = $user_info;

                if (count($users) >= $num) {
                    break;
                }
            }

            $offset = $offset + $k + 1;
            if (count($list) > $num) {
                $more = true;
            }
        }

        $counter = CounterService::get(CounterService::COUNTER_TYPE_FOLLOWERS, $uid);

        $is_remind_forbidden = $isforbidden || $counter >= 200;

        return apiRender(array(
            "users" => $users,
            "offset" => $offset,
            "more" => $more,
            "is_remind_forbidden" => $is_remind_forbidden
        ));
    }

    /**
     * 添加关注
     */
    public function add(Request $request)
    {
        $loginid = Context::get("userid");
        $uid = (int)$request->post("uid", 0);

        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");

        $liveid = (int)$request->post("liveid", 0);

        $counters = CounterService::get(CounterService::COUNTER_TYPE_FOLLOWINGS, $loginid);
        $followings = (int)$counters;

        Interceptor::ensureNotFalse($followings < self::FOLLOWING_LIMIT, ERROR_FOLLOW_TOO_MUCH);

        $followed = FollowService::isFollowed($loginid, $uid);

        if ($followed[$uid]) {
            Interceptor::ensureNotEmpty($liveid, ERROR_FOLLOW_FOLLOWED);
        }

        if ($loginid != $uid) {
            $following_userinfo = UserService::getUserInfo($uid);

            Interceptor::ensureNotFalse($following_userinfo && !ForbiddenService::isForbidden($uid), ERROR_USER_NOT_EXIST);

            Interceptor::ensureFalse(BlockedService::exists($uid, $loginid), ERROR_USER_BLOCKED);

            $followed = FollowService::addFollow($loginid, $uid, $liveid);
        }

        return apiRender($followed);
    }

    /**
     * 批量关注
     */
    public function multiAdd(Request $request)
    {
        $loginid = Context::get("userid");
        $fids = trim($request->post("fids"), ",");
        Interceptor::ensureNotFalse(preg_match("/^(\d+,?)+$/", $fids) != 0 && substr_count($fids, ",") < 20, ERROR_PARAM_INVALID_FORMAT, "fids($fids)");

        $list = explode(",", $fids);

        try {
            $counters = CounterService::get(CounterService::COUNTER_TYPE_FOLLOWINGS, $loginid);
            $followings = (int)$counters;
        } catch (Exception $e) {
            $followings = 0;
        }

        Interceptor::ensureNotFalse($followings < self::FOLLOWING_LIMIT, ERROR_FOLLOW_TOO_MUCH);

        $filter_list = array();
        $forbidden_users = ForbiddenService::isForbiddenUsers($list);

        foreach ($list as $uid) {
            if ($loginid != $uid) {
                $following_userinfo = UserService::getUserInfo($uid);

                if (!$following_userinfo || in_array($following_userinfo["uid"], $forbidden_users)) {
                    continue;
                }

                $filter_list[] = $uid;
            }
        }

        $followed = FollowService::addFollow($loginid, $filter_list, "multi");

        return apiRender();
    }

    /**
     * 取消关注
     */
    public function cancel(Request $request)
    {
        $loginid = Context::get("userid");
        $uid = (int)$request->post("uid", 0);
        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid");

        FollowService::cancelFollow($loginid, $uid);

        return apiRender();
    }

    /**
     * 关注用户 信息配置
     */
    public function setOptionNotice(Request $request)
    {
        $loginid = Context::get("userid");
        $fid = (int)$request->post("fid");
        $notice = trim($request->post("notice", "N"));

        Interceptor::ensureNotFalse($fid > 0, ERROR_PARAM_INVALID_FORMAT, "fid");
        Interceptor::ensureNotFalse(in_array($notice, array("Y", "N")), ERROR_PARAM_INVALID_FORMAT, "notice");

        FollowService::setOptionNotice($loginid, $fid, $notice);

        return apiRender();
    }
}
