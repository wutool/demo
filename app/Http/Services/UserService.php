<?php


namespace App\Http\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Http\Services\ForbiddenService;
use App\Http\Services\UserExpService;
use App\Http\Services\UserMedalService;
use App\Http\Services\VipService;
use App\Http\Services\SessionService;
use App\Jobs\UserRegisterChannelJob;
use App\Jobs\OldUserLoginlJob;
use App\Models\LoginLog;
use App\Models\LoginErrorLog;
use App\Models\User;
use App\Models\UserBind;
use App\Models\UserEnter;
use App\Models\Anchor;
use App\Models\Vip;
use App\Util\Interceptor;
use App\Util\Context;
use App\Util\Util;
use App\Util\Captcha;
use App\Util\Aes;

class UserService
{
    public function register($username, $nickname, $password, $signature, $deviceid, $platform, $channel)
    {
        $nickname = $this->fixNickname($nickname);

        $data = [
            'username' => $username,
            'nickname' => $nickname,
            'password' => $password,
            'signature' => $signature,
            'identifier' => $deviceid,
        ];

        $this->userValidator($data);

        // 登录账号判断
        Interceptor::ensureNotFalse($this->resentUsername($username) <= 0, ERROR_USER_NAME_EXISTS, '');
        $daoUser = new User();
        Interceptor::ensureNull($daoUser->findByUserName($username), ERROR_USER_NAME_EXISTS, $username);

        # 注册频次判断
        $ip = ip2long(Util::getIP());
        #Interceptor::ensureNotFalse(Captcha::checkIntervalTime($ip, 'register'), ERROR_REG_INTERVAL_TIME);
        #Interceptor::ensureNotFalse(Captcha::checkSendTimes($ip, 'register'), ERROR_REG_INTERVAL_TIME);

        # 按设备号判断频次
        $checkDeviceid = md5($deviceid);
//        Interceptor::ensureNotFalse(Captcha::checkIntervalTime($checkDeviceid, 'register'), ERROR_REG_INTERVAL_TIME);
//        Interceptor::ensureNotFalse(Captcha::checkSendTimes($checkDeviceid, 'register'), ERROR_REG_INTERVAL_TIME);

        $avatar = $this->getRandAvatar();
        $now = date('Y-m-d H:i:s');

        try {
            DB::connection('old')->beginTransaction();

            $uid = $daoUser->register($username, $nickname, $avatar, $platform, $channel, $now, $now);
            $this->setPassword($uid, $password);

            DB::connection('old')->commit();

            Captcha::action($ip, 'register');

        } catch (\Exception $ex) {
            DB::connection('old')->rollback();

            Log::error("[注册失败][code:{$ex->getCode()}][{$ex->getMessage()}]");

            throw new ApiException(ERROR_SYS_DB_SQL);
        }

        $this->saveResentUsername($username);

        $token = SessionService::getToken($uid);
        $this->addLoginLog($uid);

        # 旧api靠着表里的token
        $this->setOldToken($uid, $token);

        $userinfo = self::getUserInfo($uid);
        $userinfo["token"] = $token;

        # 分配渠道号
        dispatch(new UserRegisterChannelJob(Context::get('channel'), Util::getIP(), date('Y-m-d H:i:s'), Context::get('platform'), Context::get('deviceid'), $uid));

        return $userinfo;
    }

    private function resentUsername($username)
    {
        $round = (int)ceil(date('i') / 5);
        $key = "repeat_username_" . $round;
        $cache = Redis::connection('common');

        return $cache->sismember($key, $username);
    }

    private function saveResentUsername($username)
    {
        $round = (int)ceil(date('i') / 5);
        $key = "repeat_username_" . $round;
        $cache = Redis::connection('common');

        $result = $cache->sadd($key, $username);
        if ($result) {
            $cache->expire($key, 900);
        }

        return $result;
    }

    public function editUser($uid, $nickname, $sex, $birthday, $location, $signature, $avatar)
    {
        $userInfo = User::where('id', $uid)->first();

        # 昵称修改限制
        $nickname = $this->fixNickname($nickname);
        $nicknameLimit = $this->editNicknameLimit($userInfo, $nickname, $uid);

        $data = [
            'nickname' => $nickname,
            'sex' => $sex,
            'birthday' => $birthday,
            'location' => $location,
            'signature' => $this->fixSignature($signature),
            'constellation' => $this->getConstellation($birthday, 1),
        ];

        if ($avatar) {
            $data['avatar'] = $avatar;
        }

        if ($nicknameLimit) {
            $data['name_update_time'] = $nicknameLimit['name_update_time'];
            $data['remain_times'] = $nicknameLimit['remain_times'];
        }

        $state = User::where('id', $uid)->update($data);

        # 修改 修改昵称时间
        if ($state && $userInfo['nickname'] != $data['nickname'] && $userInfo['role'] == 2) {
            Anchor::where('user_id', $uid)->update(['name_update_time' => date('Y-m-d H:i:s')]);
        }
        self::reload($uid);
    }

    public function modifyUser($uid, $nickname, $signature, $password)
    {
        $user = new User();
        $userInfo = $user->getUserInfo($uid);
        Interceptor::ensureNotEmpty($userInfo, ERROR_PARAM_INVALID_FORMAT, 'uid');

        $data = [
            'nickname' => $nickname,
            'signature' => $this->fixSignature($signature),
        ];
        $state = User::where('id', $uid)->update($data);

        if ($password) {
            $this->setPassword($uid, $password);
        }

        # 计算消费等级
        UserExpService::addUserExp($uid, 0);

        self::reload($uid);

        return $state;
    }

    private function editNicknameLimit($userInfo, $nickname, $uid)
    {
        $data = [];

        //一个月只能修改一次昵称
        if ($userInfo['role'] == 2 && $userInfo['nickname'] != $nickname) {
            $zhubo_info = Anchor::where('user_id', $uid)->first();
            if ($zhubo_info) {
                $the_date = date('Y-m' . '-01 00:00:00');
                Interceptor::ensureNotFalse(($the_date < $zhubo_info['name_update_time'] && $zhubo_info['name_update_time']),
                    ERROR_USER_AUTHOR_NICKNAMELIMIT);
            }
        }

        //用户每月修改昵称次数限制
        if ($userInfo['role'] == 1 && $userInfo['nickname'] != $nickname) {
            $thisMonth = date('Y-m' . '-01 00:00:00');
            $thisTime = date('Y-m-d H:i:s');
            if ($userInfo->vip > 0) {
                $nicknameLimits = Vip::query()->pluck('nickname_limit', 'level')->all();
                $limitTimes = $nicknameLimits[$userInfo->vip];
            } else {
                $nicknameLimits = DB::connection('old')
                    ->table('qvod_user_exp_level_title')
                    ->select('nickname_limit')
                    ->where('level', $userInfo->level)
                    ->first();
                $limitTimes = $nicknameLimits->nickname_limit;
            }
            // 剩余修改次数
            if ($limitTimes != 0) {
                if ($userInfo->name_update_time && $userInfo->name_update_time > $thisMonth) {
                    if (isset($userInfo->remain_times) && $userInfo->remain_times >= $limitTimes) {
                        Interceptor::ensureNotFalse(false, ERROR_USER_NICKNAMELIMIT, [$limitTimes, $limitTimes]);
                    } else {
                        $data['name_update_time'] = $thisTime;
                        $data['remain_times'] = $userInfo->remain_times + 1;
                    }
                } else {
                    $data['name_update_time'] = $thisTime;
                    $data['remain_times'] = 1;
                }
            }
        }

        return $data;
    }

    private function setPassword($uid, $password)
    {
        $daoUser = new User();

        $salt = rand(100000, 999999);
        $password = sha1($salt . $password);

        return $daoUser->setPassword($uid, $password, $salt);
    }

    public function getCode($uid, $type, $mobile)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBind($uid, 'mobile');
        switch ($type) {
            case 'bind':
                # 绑定
                Interceptor::ensureEmpty($bindInfo, BIZ_PASSPORT_ERROR_BINDED_OTHER, ['号码']);
                break;
            case 'unbind':
                # 解绑
                Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);
                break;
            case 'reset':
                # 修改密码
                Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);
                Interceptor::ensureNotFalse(
                    $mobile == $bindInfo['rid'],
                    ERROR_PHONENUM_INVALID
                );
                break;
        }

        $id = Captcha::send($mobile, $type);

        return $id;
    }

    public function getBinds($uid)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBind($uid, 'mobile');

        $result = [];
        if ($bindInfo) {
            $item = [
                "source" => 'phone',
                "rid" => $bindInfo['rid'],
            ];

            $result[] = $item;
        }

        return $result;
    }

    public function bind($uid, $mobile, $code, $password)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBind($uid, 'mobile');

        Interceptor::ensureEmpty($bindInfo, BIZ_PASSPORT_ERROR_BINDED_OTHER, ['号码']);
        # 验证下发代码
        Interceptor::ensureNotFalse(Captcha::verify($mobile, $code, "bind"), ERROR_CODE_INVALID);

        $userInfo = User::select(['salt', 'password'])->where('id', $uid)->first();
        Interceptor::ensureNotFalse((sha1($userInfo->salt . $password) === $userInfo->password), ERROR_USER_PASSWORD_WRONG);

        $daoUserBind->addUserBind($uid, $mobile, 'mobile', '', '', '');
    }

    public function unbind($uid, $code, $password)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBind($uid, 'mobile');

        Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);
        $mobile = $bindInfo['rid'];

        # 验证下发代码
        Interceptor::ensureNotFalse(Captcha::verify($mobile, $code, "unbind"), ERROR_CODE_INVALID);

        $userInfo = User::select(['salt', 'password'])->where('id', $uid)->first();
        Interceptor::ensureNotFalse((sha1($userInfo->salt . $password) === $userInfo->password), ERROR_USER_PASSWORD_WRONG);

        $daoUserBind->deleteUserBind($uid, 'mobile');
    }

    public function reset($uid, $code, $password)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBind($uid, 'mobile');

        Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);
        $mobile = $bindInfo['rid'];

        Interceptor::ensureNotFalse(Captcha::verify($mobile, $code, "reset"), ERROR_CODE_INVALID);

        $this->setPassword($uid, $password);
        $token = SessionService::getToken($uid);

        $this->setOldToken($uid, $token);

        return $token;
    }

    public function getForgotCode($mobile, $type)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBindBySource($mobile, 'mobile');

        Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);

        $mobile = $bindInfo['rid'];
        $id = Captcha::send($mobile, $type);

        return $id;
    }

    /**
     * 忘记密码找回
     */
    public function forgot($mobile, $code, $password)
    {
        $daoUserBind = new UserBind();
        $bindInfo = $daoUserBind->getUserBindBySource($mobile, 'mobile');

        Interceptor::ensureNotEmpty($bindInfo, BIZ_PASSPORT_ERROR_NOT_BIND);
        $mobile = $bindInfo['rid'];

        # 验证下发代码
        Interceptor::ensureNotFalse(Captcha::verify($mobile, $code, "forgot"), ERROR_CODE_INVALID);

        $uid = $bindInfo['uid'];
        $this->setPassword($uid, $password);
        $token = SessionService::getToken($uid);

        $this->setOldToken($uid, $token);

        return $token;
    }

    public function login($username, $password)
    {
        $daoUser = new User();
        $user = $daoUser->findByUserName($username);

        Interceptor::ensureNotNull($user, ERROR_USER_PASSWORD_WRONG);

        $uid = $user->uid;

        # 封禁
        if (ForbiddenService::isForbidden($uid)) {
            Interceptor::ensureNotFalse(false, ERROR_USER_ERR_BLACK, [$uid]);
        }

        if (sha1($user->salt . $password) !== $user->password) {
            $daoErrorlog = new LoginErrorLog();
            $daoErrorlog->addLog(null, $username, ERROR_USER_PASSWORD_WRONG);
            # 密码错误
            Interceptor::ensureNotFalse(false, ERROR_USER_PASSWORD_WRONG);
        }

        $userinfo = self::getUserInfo($uid);
        $userinfo["token"] = SessionService::getToken($uid);

        $this->addLoginLog($uid);

        # 兼容旧token
        $this->setOldToken($uid, $userinfo["token"]);

        return $userinfo;
    }

    public function fastLogin($uid)
    {
        $userinfo = UserService::getUserInfo($uid);

        Interceptor::ensureNotEmpty($userinfo, ERROR_LOGINUSER_NOT_EXIST);

        # 重新生成token
        $userinfo["token"] = SessionService::getToken($uid);

        # 记录登录日志
        $this->addLoginLog($uid);

        $this->setOldToken($uid, $userinfo["token"]);

        return $userinfo;
    }

    public function addLoginLog($uid)
    {
        $daoLoginlog = new LoginLog();
        $extend = json_encode(array());

        return $daoLoginlog->addLoginLog($uid, Util::getIP(), Context::get("deviceid"), Context::get("platform"), Context::get("version"), Context::get("brand"), Context::get("model"), Context::get("network"), Context::get("netspeed"), $extend);
    }

    public static function reload($uid)
    {
        $cache = Redis::connection('user');
        $cache->del("USER_CACHE_{$uid}");

        self::getUserInfo($uid);

        return true;
    }

    public static function getUserInfo($uid)
    {
        $cache = Redis::connection('user');
        $key = "USER_CACHE_{$uid}";

        if (!($userinfo = $cache->get($key))) {
            $daoUser = new User();
            $userinfo = $daoUser->getUserInfo($uid);
            if ($userinfo) {
                # 经验
                $exp = UserExpService::getUserExp($uid, $userinfo);
                # 勋章
                $medal = UserMedalService::getUserMedals($uid, $userinfo);

                $userinfo = array(
                    "uid" => (int)$userinfo["uid"],
                    "nickname" => (string)$userinfo["nickname"],
                    "avatar" => (string)$userinfo["avatar"],
                    "signature" => (string)$userinfo["signature"],
                    "sex" => (string)$userinfo["sex"],
                    "birthday" => (string)$userinfo["birthday"],
                    "constellation" => (string)self::getConstellation($userinfo["birthday"]),
                    "exp" => (int)$exp,
                    "level" => (int)UserExpService::getLevelByExp($uid, $userinfo),
                    "vip" => (int)VipService::getUserVipLevel($uid, $userinfo['vip']),
                    "medal" => (object)$medal,
                    'channel' => (string)$userinfo['channel'],
                    'location' => (string)$userinfo['location'],
                    "L2_cached_time" => $_SERVER["REQUEST_TIME"],
                );
                $cache->set($key, json_encode($userinfo), 'EX', 864000);
            }
        } else {
            $userinfo = json_decode($userinfo, true);
            $userinfo['medal'] = $userinfo['medal'] ? $userinfo['medal'] : new \stdClass();
            $userinfo["L2_cached"] = true;
        }

        return $userinfo;
    }

    private function getRandAvatar()
    {
        $avatar = [

        ];
        $key = array_rand($avatar);

        return $avatar[$key];
    }

    private static function getConstellation($date, $type = null)
    {
        $m = (int)date('m', strtotime($date));
        $d = (int)date('d', strtotime($date));
        $xzdict = array('摩羯', '水瓶', '双鱼', '白羊', '金牛', '双子', '巨蟹', '狮子', '处女', '天秤', '天蝎', '射手');
        $zone = array(1222, 122, 222, 321, 421, 522, 622, 722, 822, 922, 1022, 1122, 1222);
        if ((100 * $m + $d) >= $zone[0] || (100 * $m + $d) < $zone[1]) {
            $i = 0;
        } else {
            for ($i = 1; $i < 12; $i++) {
                if ((100 * $m + $d) >= $zone[$i] && (100 * $m + $d) < $zone[$i + 1]) {
                    break;
                }
            }
        }
        $name = $xzdict[$i] . '座';

        $all = [
            "1" => "摩羯座",
            "2" => "水瓶座",
            "3" => "水瓶座",
            "4" => "双鱼座",
            "5" => "双鱼座",
            "6" => "白羊座",
            "7" => "白羊座",
            "8" => "金牛座",
            "9" => "金牛座",
            "10" => "双子座",
            "11" => "双子座",
            "12" => "巨蟹座",
            "13" => "巨蟹座",
            "14" => "狮子座",
            "15" => "狮子座",
            "16" => "处女座",
            "17" => "处女座",
            "18" => "天枰座",
            "19" => "天秤座",
            "21" => "天蝎座",
            "22" => "射手座",
            "23" => "射手座",
            "24" => "摩羯座",
        ];
        $all = array_flip($all);

        return $type ? $all[$name] : $name;
    }

    private function userValidator($data)
    {
        Interceptor::ensureNotFalse(
            preg_match("/^[a-zA-Z0-9]{6,16}$/", $data['username']) > 0,
            ERROR_USER_REG_INVALID,
            '登录账号由6-16位字母或数字组成'
        );

        Interceptor::ensureNotFalse(
            preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]{2,8}$/u", $data['nickname']) > 0,
            ERROR_USER_REG_INVALID,
            '昵称由2-8位中文、字母或数字组成'
        );

        Interceptor::ensureNotFalse(
            preg_match("/^[a-zA-Z0-9]{6,16}$/", $data['password']) > 0,
            ERROR_USER_REG_INVALID,
            '密码由6-16位的英文字母或数字组成'
        );

        Interceptor::ensureNotFalse(strlen($data['identifier']) > 0, ERROR_PARAM_IS_EMPTY, 'identifier');
    }

    private function fixNickname($nickname)
    {
        $replace = [

        ];

        $nickname = str_replace($replace, '', $nickname);

        $length = mb_strlen($nickname);
        if ($length < 2) {
            $nickname = '伊人' . mt_rand(111111, 999999);
        } elseif ($length > 8) {
            $nickname = mb_substr($nickname, 0, 8);
        }

        if (preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]{2,8}$/u", $nickname) <= 0) {
            $nickname = '1' . mt_rand(111111, 999999);
        }

        return $nickname;
    }

    private function fixSignature($signature)
    {
        $length = mb_strlen($signature);
        if ($length > 20) {
            $signature = mb_substr($signature, 0, 8);
        }

        $replace = [

        ];

        foreach ($replace as $value) {
            if (strpos($signature, $value) !== false) {
                $signature = '';
                break;
            }
        }
        unset($value);

        return $signature;
    }

    public function setOldToken($uid, $token)
    {
        $now = date("Y-m-d H:i:s");
        Db::connection('old')->insert("replace into qvod_token (uid, api_token, addtime) values(?,?,?)",
            [$uid, $token, $now]);
    }

    public function loginOut($uid)
    {
        # 重置token
        SessionService::getToken($uid);
        Db::connection('old')->table('qvod_token')
            ->where('uid', '=', $uid)
            ->delete();
    }

    public static function getUserInfos($uids)
    {
        $uids = array_filter($uids);
        $result = $arrKey = array();
        foreach ($uids as $uid) {
            $arrKey[] = "USER_CACHE_{$uid}";
        }
        unset($uid);

        $cache = Redis::connection('user');
        $list = $cache->mget($arrKey);
        if ($list) {
            foreach ($list as $item) {
                if ($item) {
                    $userInfo = json_decode($item, true);
                    $result[$userInfo['uid']] = $userInfo;
                }
            }
            unset($item);
        }

        $uid = array_keys($result);;
        $relateidsDb = array_diff($uids, $uid);

        foreach ($relateidsDb as $value) {
            $userInfo = UserService::getUserInfo($value);
            if ($userInfo) {
                $result[$value] = $userInfo;
            }
        }
        return $result;
    }
}
