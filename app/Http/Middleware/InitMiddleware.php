<?php


namespace App\Http\Middleware;

use Closure;

use App\Util\Interceptor;
use App\Util\Consume;
use App\Util\Context;
use App\Util\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InitMiddleware
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
        Consume::start();

        $deviceid = substr(trim(strip_tags($request->query("deviceid"))), 0, 36);
        $userid = (int)$request->query("userid");
        $platform = trim(strip_tags($request->query("platform", "android")));
        $version = trim($request->query("version"));
        $network = trim(strip_tags($request->query("network")));
        $netspeed = trim(strip_tags($request->query("netspeed")));
        $rand = trim(strip_tags($request->query("rand")));
        $time = trim(strip_tags($request->query("time")));
        $channel = trim(strip_tags($request->query("channel")));
        $armour = trim(strip_tags($request->query("armour")));
        $lng = trim(strip_tags($request->query('lng')));
        $lat = trim(strip_tags($request->query('lat')));
        $os = trim(strip_tags($request->query('os')));

        Context::add("userid", $userid);
        Context::add("deviceid", $deviceid);
        Context::add("version", $version);
        Context::add("user_agent", array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER["HTTP_USER_AGENT"] : '');
        Context::add("platform", $platform);
        Context::add("brand", trim(strip_tags($request->input("brand"))));
        Context::add("model", trim(strip_tags($request->input("model"))));
        Context::add("network", $network);
        Context::add("netspeed", $netspeed);
        Context::add("region", '');
        Context::add("channel", $channel);
        Context::add("armour", $armour);
        Context::add("lng", $lng);
        Context::add("lat", $lat);
        Context::add("os", $os);

        Interceptor::ensureNotFalse(in_array($platform, array("android", "ios", "server", "admin", "h5", "y8r3e1gopuf09zd6tkjw2mvbci75n4xshqlauk")), ERROR_PARAM_PLATFORM_INVALID);

        $guid = $request->input("guid");

        $message = [
            'name' => 'api_debug',
            'url' => str_replace(['http://', 'https://'], ['*ttp://', '*ttps://'], $request->fullUrl()),
            'token' => trim($request->cookie("token")),
            'post' => $request->post(),
        ];
        Log::debug(json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        switch ($platform) {
            case 'android':
            case 'ios':
                Interceptor::ensureNotFalse(Util::isValidClient($userid, $deviceid, $platform, $network, $version, $rand, $netspeed, $time, $guid), ERROR_PARAM_SIGN_INVALID);
                Interceptor::ensureNotFalse(Util::checkFlood($guid), ERROR_PARAM_FLOOD_REQUEST);
                break;
            case 'server':
                # server
                Interceptor::ensureNotFalse(Util::isValidServer($rand, $time, $guid), ERROR_PARAM_SIGN_INVALID);
                break;
            case 'admin':
                Interceptor::ensureNotFalse(Util::isValidAdmin($rand, $time, $guid), ERROR_PARAM_SIGN_INVALID);
                break;
            case 'h5':
                Interceptor::ensureNotFalse(Util::isValidCdn($rand, $time, $guid), ERROR_PARAM_SIGN_INVALID);
                Interceptor::ensureNotFalse(Util::checkFlood($guid), ERROR_PARAM_FLOOD_REQUEST);
                break;
            default:
                Interceptor::ensureNotFalse(false, ERROR_PARAM_SIGN_INVALID);
                break;
        }

        return $next($request);
    }
}
