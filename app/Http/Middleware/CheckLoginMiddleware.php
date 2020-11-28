<?php

namespace App\Http\Middleware;

use Closure;

use App\Http\Services\SessionService;
use App\Http\Services\UserService;
use App\Util\Interceptor;
use App\Util\Context;
use Illuminate\Http\Request;

class CheckLoginMiddleware
{
    /**
     * Run the request filter.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        # 接口需要登录
        $token = trim($request->cookie("token"));

        if (strlen($token) == 38) {
            Interceptor::ensureNotFalse(SessionService::isLogined($token), ERROR_USER_ERR_TOKEN);
            # 覆盖接口给的userid
            Context::set("userid", SessionService::getLoginId($token));
        } else {
            Interceptor::ensureNotFalse(false, ERROR_USER_ERR_TOKEN);
        }

        return $next($request);
    }
}
