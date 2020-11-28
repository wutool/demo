<?php


namespace App\Http\Middleware;

use Closure;

use Illuminate\Http\Request;
use App\Http\Services\ForbiddenService;
use App\Http\Services\UserService;
use App\Models\Forbidden;
use App\Util\Interceptor;
use App\Util\Context;

class CheckForbiddenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $uid = Context::get("userid");
        $forbiddened = ForbiddenService::isForbidden($uid);

        if ($forbiddened) {
            Interceptor::ensureNotFalse(false, ERROR_USER_ERR_BLACK);
        }

        return $next($request);
    }
}
