<?php


namespace App\Http\Middleware;


use App\Util\Context;
use App\Util\Interceptor;
use Closure;
use Illuminate\Http\Request;

class CheckAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $platform = Context::get("platform");

        Interceptor::ensureNotFalse($platform == 'admin', ERROR_API_NOT_ALLOWED);

        return $next($request);
    }
}
