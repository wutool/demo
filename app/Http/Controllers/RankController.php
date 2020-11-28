<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\RankService;
use Illuminate\Support\Facades\Redis;
use App\Util\Interceptor;

/**
 * 排行榜
 *
 * Class RankController
 * @package App\Http\Controllers
 */
class RankController extends Controller
{
    public function getRanking(Request $request)
    {
        $name = trim(strip_tags($request->post("name", '')));
        $offset = (int)$request->post("offset", 0);
        $num = (int)$request->post("num", 10);

        if ($num == 3) {
            # 排行榜前三位
        } else {
            $num = 10;
        }

        // fix 安卓旧版本
        if ($name == 'receivegift_ranking_week_5') {
            $name = 'receivegift_ranking_week_05';
        }
        if ($name == 'sendgift_ranking_week_5') {
            $name = 'sendgift_ranking_week_05';
        }
        if ($name == 'receivegift_ranking_week_4') {
            $name = 'receivegift_ranking_week_04';
        }
        if ($name == 'sendgift_ranking_week_4') {
            $name = 'sendgift_ranking_week_04';
        }

        $rank = new RankService();

        if (0 === strpos($name, 'follower_ranking')) {
            list($total, $ranking, $offset, $more) = $rank->getRankingFollowers($name, $num);
        } else {
            list($total, $ranking, $offset, $more) = $rank->getRanking($name, $offset, $num);
        }

        $decrease = false;
        if (strncmp($name, 'receivegift', strlen('receivegift')) === 0) {
            $decrease = true;
        }

        if ($decrease) {
            foreach ($ranking as &$value) {
                $value['score'] = (int)ceil($value['score'] / 100);
            }
            unset($value);
        }

        return apiRender(array("total" => $total, "ranking" => $ranking, "offset" => $offset, "more" => $more));
    }

    /**
     * todo 直播间真实在线人数
     */
    public function getLiveUserNum(Request $request)
    {
        $liveids = $request->post("liveids") ? trim(strip_tags($request->post("liveids"))) : "";

        $liveid_array = explode(",", $liveids);
        Interceptor::ensureNotEmpty($liveid_array, ERROR_PARAM_IS_EMPTY, "liveids");

        $list = array();

        $cache = Redis::connection('cache');
        foreach ($liveid_array AS $key => $val) {
            $key = "zb_live_user_real_num_" . $val;
            $result = json_decode($cache->get($key), true);
            $list[$val] = $result['num'] ? $result['num'] : 0;
        }

        return apiRender($list);
    }

    /**
     * 获取榜单排行值
     */
    public function getRankingElement(Request $request)
    {
        $name = $request->post("name") ? trim(strip_tags($request->post("name"))) : "";
        $uid = $request->post("uid") ? (int)$request->post("uid") : 0;

        $rank = new RankService();

        $rank_value = 0;
        if (strpos($name, 'date') !== false) {
            if ((int)date('YmdH') < (int)date("Ymd") . '05') {
                $name = str_replace('date', 'date_' . date("Ymd", strtotime("-1 day ")) . '05', $name);
            } else {
                $name = str_replace('date', 'date_' . date("Ymd") . '05', $name);
            }
        } else {
            $name = str_replace('week', 'week_' . date("W"), $name);
            $name = str_replace('month', 'month_' . date("Ym"), $name);
        }

        Interceptor::ensureNotFalse($uid >= 0, ERROR_PARAM_NOT_SMALL_ZERO, "uid");

        $score = $rank->getRankingElement($name, $uid);
        $score = $score ? $score : 0;
        return apiRender(array('score' => $score, "rank" => $rank_value));
    }
}
