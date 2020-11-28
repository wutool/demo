<?php


namespace App\Http\Services;


use Illuminate\Support\Facades\Redis;

class RankService
{
    private static $hidden_users = [];

    /**
     * 更新排行榜逻辑
     *
     */
    public function setRank($type, $action, $element, $score, $relateid = 0, $liveid = 0)
    {

        switch ($type) {
            case "following"://关注榜
                $this->setRankFollowing($action, $element, $score);
                break;
            case "followers"://粉丝榜
                $this->setRankFollowers($action, $element, $score);
                break;
            case "protect"://守护榜
                $this->setRankProtect($action, $element, $score, $relateid);
                break;
            case "audience"://观众榜 包括机器人
                $this->setRankAudience($action, $element, $score, $relateid);
                break;
            case "praise"://点赞榜
                $this->setRankPraise($action, $element, $score);
                break;
            case "sendgift"://送礼榜
                $this->setRankSendGift($action, $element, $score, $relateid, $liveid);
                break;
            case "receivegift"://收礼榜
                $this->setRankReceiveGift($action, $element, $score);
                break;
            case 'intimacy':    // 亲密度排行
                $this->setIntimacy($action, $element, $score, $relateid);
                break;
            case "realaudience"://真实观众榜 不包括机器人
                $name = "realaudience_" . $relateid;
                $this->_sync($name, $action, $element, $score);
                break;
            case "userguard"://守护icon
                $name = "userguard_" . $relateid;
                $this->_sync($name, $action, $element, $score);
                break;

            default:
                break;
        }
        return true;
    }

    /**
     * 关注榜
     * @parshoum string $action
     * @param string $element
     * @param int $score
     */
    private function setRankFollowing($action, $element, $score)
    {
        $key_total = "following_ranking";//总榜
        $key_date = "following_ranking_date_" . date("Ymd");//日榜
        $key_week = "following_ranking_week_" . date('W');//周榜
        $key_month = "following_ranking_month_" . date("Ym");//月榜

        $this->_sync($key_total, $action, $element, $score);
        $this->_sync($key_date, $action, $element, $score);
        $this->_sync($key_week, $action, $element, $score);
        $this->_sync($key_month, $action, $element, $score);

        return true;
    }

    /**
     * 粉丝榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankFollowers($action, $element, $score)
    {
        $key_total = "follower_ranking";//总榜
//        $key_date = "follower_ranking_date_" . date("Ymd");
//        $key_week = "follower_ranking_week_" . date('W');//日榜
//        $key_month = "follower_ranking_month_" . date("Ym");//日榜

        $this->_sync($key_total, $action, $element, $score);
//        $this->_sync($key_date, $action, $element, $score);
//        $this->_sync($key_week, $action, $element, $score);
//        $this->_sync($key_month, $action, $element, $score);

        return true;
    }

    /**
     * 守护榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankProtect($action, $element, $score, $relateid)
    {
        $key_total = "protect_" . $relateid;//总榜
        $key_date = "protect_ranking_" . $relateid . "_date_" . date("Ymd");//日榜
        $key_week = "protect_ranking_" . $relateid . "_week_" . date('W');//周榜
        $key_month = "protect_ranking_" . $relateid . "_month_" . date("Ym");//月榜

        $this->_sync($key_total, $action, $element, $score);
        $this->_sync($key_date, $action, $element, $score);
        $this->_sync($key_week, $action, $element, $score);
        $this->_sync($key_month, $action, $element, $score);

        return true;
    }

    /**
     * 观众榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankAudience($action, $element, $score, $relateid)
    {
        $key_total = "audience_" . $relateid;

        $this->_sync($key_total, $action, $element, $score);

        return true;
    }

    /**
     * 点赞榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankPraise($action, $element, $score)
    {
        $key_total = "praise_ranking";//总榜

        $key_date = "praise_ranking_date_" . date("Ymd");
        $key_week = "praise_ranking_week_" . date('W');//日榜
        $key_month = "praise_ranking_month_" . date("Ym");//日榜

        $this->_sync($key_total, $action, $element, $score);
        $this->_sync($key_date, $action, $element, $score);
        $this->_sync($key_week, $action, $element, $score);
        $this->_sync($key_month, $action, $element, $score);

        return true;
    }

    /**
     * 送礼榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankSendGift($action, $element, $score, $relateid, $liveid)
    {
        $key_total = "sendgift_ranking";//总榜
        $key_date = "sendgift_ranking_date_" . date("Ymd");// 日榜
        $key_week = "sendgift_ranking_week_" . date('W'); //周榜
        $key_month = "sendgift_ranking_month_" . date("Ym");//月榜

        $this->_sync($key_total, $action, $element, $score);
        $this->_sync($key_date, $action, $element, $score);
        $this->_sync($key_week, $action, $element, $score);
        $this->_sync($key_month, $action, $element, $score);

        // 亲密度
        if ($relateid) {
            $this->setIntimacy($action, $element, $score, $relateid);
        }

        // 直播间消费
        if ($liveid) {
            $key_live = "sendgift_ranking_live_" . $liveid;
            $this->_sync($key_live, $action, $element, $score);
        }

        return true;
    }

    /**
     * 收礼榜
     * @param string $action
     * @param string $element
     * @param int $score
     */
    private function setRankReceiveGift($action, $element, $score)
    {
        $key_total = "receivegift_ranking";//总榜

        $key_date = "receivegift_ranking_date_" . date("Ymd");//日榜
        $key_week = "receivegift_ranking_week_" . date('W');//周榜
        $key_month = "receivegift_ranking_month_" . date("Ym");//月榜

        $this->_sync($key_total, $action, $element, $score);
        $this->_sync($key_date, $action, $element, $score);
        $this->_sync($key_week, $action, $element, $score);
        $this->_sync($key_month, $action, $element, $score);

        return true;
    }

    /**
     * 紧密度排行
     *
     * @param $action
     * @param $element  观众uid
     * @param $score    分值
     * @param $relateid uid
     * @return bool
     */
    private function setIntimacy($action, $element, $score, $relateid)
    {
        $key_total = "intimacy_" . $relateid;

        $this->_sync($key_total, $action, $element, $score);

        return true;
    }

    private function _sync($name, $action, $element, $score)
    {
        $cache = self::getRedis();

        switch ($action) {
            case "increase":
                $bool = $cache->zIncrBy($name, $score, $element);
                break;
            case "decrease":
                $cache->zIncrBy($name, 0 - $score, $element);
                break;
            case "set":
                $cache->zAdd($name, $score, $element);
                break;
            case "delete":
                $cache->zRem($name, $element);
                break;
            case "destroy":
                $cache->del($name);
                break;
        }

        return true;
    }

    public function getRanking($name, $offset, $num)
    {
        $num = ($num > 20) ? 20 : $num;

        $cache = self::getRedis();
        $total = $cache->zCard($name);

        if ($offset >= $total) {
            return array($offset, array(), $offset, false);
        }

        $elements = $cache->zRevRange($name, $offset, $offset + $num - 1);
        $users = array();

        foreach ($elements as $element) {

            if (!ForbiddenService::isForbidden($element) && !in_array($element, self::$hidden_users)) {
                $info = UserService::getUserInfo($element);
                if (!empty($info)) {
                    $score = $cache->zScore($name, $info['uid']);
                    $info['score'] = (int)$score;
                    $users[] = $info;
                } else {
                    $cache->zRem($name, $element);
                }
            }
        }

        $offset += $num;//下一页的起始值
        $offset = $offset > $total ? $total : $offset;
        $next_elements = $cache->zRevRange($name, $offset, $offset + $num);

        //判断是否有下一页
        $more = false;
        if (!empty($next_elements)) {
            $more = true;
        }

        return array($total, $users, $offset, $more);
    }

    public function getRankingFollowers($name, $resultNum)
    {
        $cache = self::getRedis();

        $num = 300;
        $offset = 0;
        $total = $resultNum;
        $elements = $cache->zRevRange($name, $offset, $offset + $num - 1);
        $users = array();

        foreach ($elements as $element) {

            if (!ForbiddenService::isForbidden($element) && !in_array($element, self::$hidden_users)) {
                $info = UserService::getUserInfo($element);
                if (!empty($info)) {

                    $medal = (array)$info['medal'];
                    if (array_key_exists('anchor_level', $medal)) {
                        $score = $cache->zScore($name, $info['uid']);
                        $info['score'] = (int)$score;
                        $users[] = $info;

                        if (count($users) >= $resultNum) {
                            break;
                        }
                    }

                } else {
                    $cache->zRem($name, $element);
                }
            }
        }

        $offset += $num;//下一页的起始值
        $offset = $offset > $total ? $total : $offset;
        $more = false;

        return array($total, $users, $offset, $more);
    }

    public function getRankingElement($name, $element)
    {
        $cache = self::getRedis();

        return $cache->ZSCORE($name, $element);
    }

    public function getRankingAll($name)
    {
        $cache = self::getRedis();
        $elements = $cache->zRevRange($name, 0, -1);

        return $elements;
    }

    /**
     * 直播间观众列表
     */
    public function getAudienceList($name, $num)
    {
        $cache = self::getRedis();
        $elements = $cache->zRevRangeByScore($name, PHP_INT_MAX, 0, ['withscores' => true, 'limit' => [0, $num]]);

        if (!$elements) {
            return [];
        }

        $addiences = array_keys($elements);

        //被封禁用户列表
        $forbiddens = ForbiddenService::isForbiddenUsers($addiences);
        $addiences = array_diff($addiences, $forbiddens);

        $userInfos = UserService::getUserInfos($addiences);

        $users = array();
        foreach ($addiences as $addience) {
            $userInfo = $userInfos[$addience];
            if ($userInfo) {
                $score = $elements[$addience];
                if (strpos($score, '.') !== false) {
                    $score = number_format($score, 3, ".", "");
                    $isPatroller = substr($score, -2, 1);//场控
                    $userInfo['isPatroller'] = (int)$isPatroller;
                } else {
                    $userInfo['isPatroller'] = 0;
                }
                $userInfo['score'] = (int)$score;

                $users[] = $userInfo;
            }
        }

        return $users;
    }

    /**
     * 计算观众列表积分
     */
    public function getAudienceScore($userInfo, $liveinfo)
    {
        $scoreVip = 7100000;
        $userid = $userInfo['uid'];
        $liveid = $liveinfo['liveid'];

        # 基础分
        $sortScore = (int)$userInfo['level'];

        $vip = (int)$userInfo['vip'];

        if ($vip > 0) {
            $sortScore += $scoreVip;
            $sortScore += $vip * 1000;
        }

        $patroller = new PatrollerService();
        $isPatroller = $patroller->isPatroller($userid, $liveinfo['uid'], $liveid);
        //判断是否是场控
        if ($isPatroller) {
            $sortScore += 0.01;
        }

        return $sortScore;
    }

    protected static function getRedis()
    {
        return Redis::connection('cache');
    }
}
