<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\ForbiddenService;
use App\Util\Interceptor;
use App\Util\Context;

/**
 * 封禁
 *
 * Class ForbiddenController
 * @package App\Http\Controllers
 */
class ForbiddenController extends Controller
{
    /**
     * 封禁
     *
     * @param Request $request
     * @return mixed
     */
    public function forbidden(Request $request)
    {
        $relateid = trim(strip_tags($request->post("relateid", "")));
        $expire = (int)$request->post("expire", 0);
        $reason = trim(strip_tags($request->post("reason")));
        $liveid = $request->post("liveid", 0);

        Interceptor::ensureNotFalse($relateid > 0, ERROR_PARAM_IS_EMPTY, "relateid");
        Interceptor::ensureNotFalse($expire > 0, ERROR_PARAM_IS_EMPTY, "expire");

        ForbiddenService::addForbidden($relateid, $expire, $reason, $liveid);

        return apiRender();
    }

    public function unForbidden(Request $request)
    {
        $relateid = trim(strip_tags($request->post("relateid", "")));

        Interceptor::ensureNotFalse($relateid > 0, ERROR_PARAM_IS_EMPTY, "relateid");
        ForbiddenService::unForbidden($relateid);

        return apiRender();
    }

    public function isForbidden(Request $request)
    {
        $userid = trim($request->post("uid"));
        Interceptor::ensureNotEmpty($userid, ERROR_PARAM_IS_EMPTY, "uid");
        $result = ForbiddenService::isForbidden($userid, Context::get("deviceid"));

        return apiRender(array(
            "result" => $result
        ));
    }

    public function isForbiddenUsers(Request $request)
    {
        $relateids = trim(strip_tags($request->post("relateids", "")));
        Interceptor::ensureNotFalse(preg_match("/^(\d+,?)+$/", $relateids) != 0, ERROR_PARAM_INVALID_FORMAT, "relateids($relateids)");

        $relateids = explode(",", $relateids);
        $relateids = array_slice($relateids, 0, 3000);

        $forbidden_info = ForbiddenService::isForbiddenUsers($relateids);

        return apiRender($forbidden_info);
    }

    /**
     * todo:封禁私信
     *
     * @param Request $request
     * @return mixed
     */
    public function forbiddenMsg(Request $request)
    {
        return apiRender();
    }

    /**
     * todo: 解封私信
     *
     * @param Request $request
     * @return mixed
     */
    public function unForbiddenMsg(Request $request)
    {
        return apiRender();
    }
}
