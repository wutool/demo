<?php


namespace App\Http\Controllers;

use App\Util\Util;
use App\Util\Captcha;
use App\Util\Interceptor;
use App\Util\Context;
use Illuminate\Http\Request;
use App\Http\Services\UserService;
use App\Http\Services\ForbiddenService;
use App\Http\Services\CounterService;
use App\Http\Services\FollowService;
use App\Http\Services\AccountService;
use App\Http\Services\ConfigService;
use App\Http\Services\ForbiddenDeviceService;

class UserController extends Controller
{
    /**
     * 注册
     */
    public function register(Request $request)
    {
        $username = trim(strip_tags($request->post('username')));
        $nickname = trim(strip_tags($request->post('nickname')));
        $password = trim(strip_tags($request->post('password')));
        $signature = trim(strip_tags($request->post('signature')));

        $platform = Context::get('platform');
        $channel = Context::get('channel');
        $deviceid = Context::get('deviceid');

        # 设备黑名单
        Interceptor::ensureFalse(ForbiddenDeviceService::isForbidden($deviceid), ERROR_USER_ERR_BLACK);

        $user = new UserService();
        $result = $user->register($username, $nickname, $password, $signature, $deviceid, $platform, $channel);

        return apiRender($result);
    }

    /**
     * 登录
     */
    public function login(Request $request)
    {
        $username = trim(strip_tags($request->post("username")));
        $password = trim(strip_tags($request->post("password")));
        $deviceid = Context::get('deviceid');

        Interceptor::ensureNotFalse(strlen($username) > 0, ERROR_PARAM_IS_EMPTY, "登录账号");
        Interceptor::ensureNotFalse(strlen($password) > 0, ERROR_PARAM_IS_EMPTY, "密码");

        # 设备黑名单
        Interceptor::ensureFalse(ForbiddenDeviceService::isForbidden($deviceid), ERROR_USER_ERR_BLACK);

        $user = new UserService();
        $userinfo = $user->login($username, $password);

        return apiRender($userinfo);
    }

    /**
     * 快速登录
     */
    public function fastLogin(Request $request)
    {
        $uid = Context::get("userid");
        $deviceid = Context::get('deviceid');

        Interceptor::ensureNotFalse($uid > 0, ERROR_LOGINUSER_NOT_EXIST);
        Interceptor::ensureFalse(ForbiddenDeviceService::isForbidden($deviceid), ERROR_USER_ERR_BLACK);

        # 记录登录日志
        $user = new UserService();
        $userinfo = $user->fastLogin($uid);

        return apiRender($userinfo);
    }

    /**
     * 获取验证码
     * bind 绑定 unbind解绑 reset重置密码
     */
    public function getCode(Request $request)
    {
        $uid = Context::get("userid");
        $type = (string)trim(strip_tags($request->post('type')));
        $mobile = trim(strip_tags($request->post('mobile')));

        Interceptor::ensureNotFalse(in_array($type, ['bind', 'unbind', 'reset'], true), ERROR_PARAM_INVALID_FORMAT, 'type');
        Interceptor::ensureNotFalse(Util::isMobile($mobile), ERROR_PHONENUM_INVALID);
        Interceptor::ensureNotFalse(Captcha::checkIntervalTime($mobile, $type), ERROR_CODE_INTERVAL_TIME, $mobile);
        Interceptor::ensureNotFalse(Captcha::checkSendTimes($mobile, $type), ERROR_CODE_OVERTIMES, $mobile);

        $user = new UserService();
        $id = $user->getCode($uid, $type, $mobile);

        return apiRender([
            'id' => $id
        ]);
    }

    public function getBinds(Request $request)
    {
        $uid = Context::get("userid");

        $user = new UserService();
        $binds = $user->getBinds($uid);

        return apiRender([
            'binds' => $binds
        ]);
    }

    public function getUserBinds(Request $request)
    {
        $uid = (int)$request->post('uid');

        $user = new UserService();
        $binds = $user->getBinds($uid);

        return apiRender([
            'binds' => $binds
        ]);
    }

    /**
     * 绑定手机
     */
    public function bind(Request $request)
    {
        $uid = Context::get("userid");
        $mobile = trim(strip_tags($request->post('mobile')));
        $code = trim(strip_tags($request->post('code')));
        $password = trim(strip_tags($request->post('password')));

        Interceptor::ensureNotFalse(strlen($mobile) > 0, ERROR_PARAM_IS_EMPTY, 'mobile');
        Interceptor::ensureNotFalse(Util::isMobile($mobile), ERROR_PHONENUM_INVALID);
        Interceptor::ensureNotFalse(strlen($password) > 0, ERROR_PARAM_IS_EMPTY, 'password');
        Interceptor::ensureNotFalse(strlen($code) > 0, ERROR_PARAM_IS_EMPTY, 'code');

        $user = new UserService();
        $user->bind($uid, $mobile, $code, $password);

        return apiRender();
    }

    /**
     * 解绑手机
     */
    public function unbind(Request $request)
    {
        $uid = Context::get("userid");
        $code = trim(strip_tags($request->post('code')));
        $password = trim(strip_tags($request->post('password')));

        Interceptor::ensureNotFalse(strlen($password) > 0, ERROR_PARAM_IS_EMPTY, 'password');
        Interceptor::ensureNotFalse(strlen($code) > 0, ERROR_PARAM_IS_EMPTY, 'code');

        $user = new UserService();
        $user->unbind($uid, $code, $password);

        return apiRender();
    }

    /**
     * 重置密码
     */
    public function reset(Request $request)
    {
        $uid = Context::get("userid");
        $code = trim(strip_tags($request->post('code')));
        # 新密码
        $password = trim(strip_tags($request->post('password')));

        Interceptor::ensureNotFalse(strlen($code) > 0, ERROR_PARAM_IS_EMPTY, 'code');
        Interceptor::ensureNotFalse(
            (preg_match("/^[A-Za-z0-9]+$/", $password)
                && mb_strlen($password) >= 6
                && mb_strlen($password) <= 16),
            ERROR_USER_PASSWORD_WRONG_FORMAT
        );

        $user = new UserService();
        $token = $user->reset($uid, $code, $password);

        return apiRender(array("token" => $token));
    }

    /**
     * 获取验证码 密码找回
     *
     * @param Request $request
     */
    public function getForgotCode(Request $request)
    {
        $mobile = trim(strip_tags($request->post('mobile')));
        $type = 'forgot';

        Interceptor::ensureNotFalse(Util::isMobile($mobile), ERROR_PHONENUM_INVALID);
        Interceptor::ensureNotFalse(Captcha::checkIntervalTime($mobile, $type), ERROR_CODE_INTERVAL_TIME, $mobile);
        Interceptor::ensureNotFalse(Captcha::checkSendTimes($mobile, $type), ERROR_CODE_OVERTIMES, $mobile);

        $user = new UserService();
        $id = $user->getForgotCode($mobile, $type);

        return apiRender([
            'id' => $id
        ]);
    }

    public function forgot(Request $request)
    {
        $mobile = trim(strip_tags($request->post('mobile')));
        $code = trim(strip_tags($request->post('code')));
        # 新密码
        $password = trim(strip_tags($request->post('password')));

        Interceptor::ensureNotFalse(Util::isMobile($mobile), ERROR_PHONENUM_INVALID);
        Interceptor::ensureNotFalse(
            (preg_match("/^[A-Za-z0-9]+$/", $password)
                && mb_strlen($password) >= 6
                && mb_strlen($password) <= 12),
            ERROR_USER_PASSWORD_WRONG_FORMAT
        );

        $user = new UserService();
        $token = $user->forgot($mobile, $code, $password);

        return apiRender(array("token" => $token));
    }

    /**
     * 编辑用户
     */
    public function edit(Request $request)
    {
        $uid = Context::get("userid");
        $nickname = trim(strip_tags($request->post('nickname', '')));
        $sex = (string)$request->post('sex', 0);
        $birthday = trim(strip_tags($request->post('birthday', '1970-01-01')));
        $location = trim(strip_tags($request->post('location', '未知')));
        $signature = trim(strip_tags($request->post('signature')));
        $avatar = trim(strip_tags($request->post('avatar')));

        Interceptor::ensureNotFalse(
            (preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $nickname)
                && mb_strlen($nickname) <= 8
                && mb_strlen($nickname) >= 2),
            ERROR_USER_NAME_INVALID
        );
        Interceptor::ensureNotFalse(strtotime($birthday), ERROR_USER_BIRTHDAY_INVALID);
        Interceptor::ensureNotFalse(in_array($sex, ['0', '1', '2'], true), ERROR_USER_GENDER_INVALID);

        $user = new UserService();
        $user->editUser($uid, $nickname, $sex, $birthday, $location, $signature, $avatar);

        return apiRender();
    }

    public function modify(Request $request)
    {
        $uid = (int)$request->post('uid', '');
        $nickname = trim(strip_tags($request->post('nickname', '')));
        $signature = trim(strip_tags($request->post('signature')));
        $password = trim(strip_tags($request->post('password')));

        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, 'uid');

        $user = new UserService();
        $user->modifyUser($uid, $nickname, $signature, $password);

        return apiRender();
    }

    /**
     * 获得用户信息
     * @param Request $request
     */
    public function getUserInfo(Request $request)
    {
        $loginid = Context::get("userid");
        $uid = (int)$request->post("uid", 0);
        Interceptor::ensureNotFalse($uid > 0, ERROR_PARAM_INVALID_FORMAT, "uid($uid)");

        $userinfo = UserService::getUserInfo($uid);
        Interceptor::ensureNotFalse($userinfo && !ForbiddenService::isForbidden($uid), ERROR_USER_NOT_EXIST);

        $counters = CounterService::mixed(
            array(
                CounterService::COUNTER_TYPE_FOLLOWERS,
                CounterService::COUNTER_TYPE_FOLLOWINGS,
            ),
            array($uid)
        );
        $userinfo["followers"] = (int)$counters[$uid][CounterService::COUNTER_TYPE_FOLLOWERS];
        $userinfo["followings"] = (int)$counters[$uid][CounterService::COUNTER_TYPE_FOLLOWINGS];

        if ($loginid > 0) {
            $userinfo["followed"] = current(FollowService::isFollowed($loginid, $uid));
        }

        return apiRender($userinfo);
    }

    /**
     * 获得多个用户信息
     *
     * @param Request $request
     */
    public function getMultiUserInfo(Request $request)
    {
        $uids = trim($request->post("uids"), ",");

        Interceptor::ensureNotFalse(preg_match("/^(\d+,?)+$/", $uids) != 0 && substr_count($uids, ",") < 100, ERROR_PARAM_INVALID_FORMAT, "uid($uids)");

        $uidList = explode(",", $uids);
        $result = array();

        $forbiddenUsers = ForbiddenService::isForbiddenUsers($uidList);

        foreach ($uidList as $uid) {
            $userinfo = UserService::getUserInfo($uid);

            if (!$userinfo || in_array($uid, $forbiddenUsers)) {
                $result[$uid] = array();
                continue;
            }

            $result[$uid] = $userinfo;

        }
        unset($uid);

        return apiRender(array(
            'users' => $result
        ));
    }

    /**
     * 获得自己的用户信息
     * @param Request $request
     */
    public function getMyUserInfo(Request $request)
    {
        $uid = Context::get("userid");

        $userinfo = UserService::getUserInfo($uid);

        $counters = CounterService::mixed(
            array(
                CounterService::COUNTER_TYPE_FOLLOWERS,
                CounterService::COUNTER_TYPE_FOLLOWINGS,
            ),
            array($uid)
        );

        $userinfo["followers"] = (int)$counters[$uid][CounterService::COUNTER_TYPE_FOLLOWERS];
        $userinfo["followings"] = (int)$counters[$uid][CounterService::COUNTER_TYPE_FOLLOWINGS];

        $diamond = AccountService::getBalance($uid, AccountService::CURRENCY_DIAMOND);
        $userinfo['diamond'] = $diamond;

        $userinfo['transfer'] = AccountService::transferWhite($userinfo);

        return apiRender($userinfo);
    }
}
