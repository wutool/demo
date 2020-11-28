<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Services\ConfigService;
use App\Util\Context;
use App\Util\Interceptor;

class ConfigController
{
    public function getConfig(Request $request)
    {
        $platform = empty($request->post('platform')) ? Context::get("platform") : $request->post('platform');

        $names = $request->post('names', '');
        $version = empty($request->post("version")) ? Context::get("version") : $request->post('version');

        Interceptor::ensureNotFalse(in_array(strtolower($platform), array("android", "ios", 'server', 'y8r3e1gopuf09zd6tkjw2mvbci75n4xshqlauk')), ERROR_PARAM_INVALID_FORMAT, "platform");
        Interceptor::ensureNotEmpty($version, ERROR_PARAM_IS_EMPTY, "version");

        $config = new ConfigService();
        $names = explode(',', $names);
        $result = $config->getConfigs($names, $platform, $version);

        return apiRender($result);
    }

    /**
     *
     * @param Request $request
     * @return mixed
     */
    public function set(Request $request)
    {
        $name = $request->post('name');
        $value = $request->post('value');
        $expire = (int)$request->post('expire');
        $platform = $request->post('platform');
        $minVersion = $request->post('min_version');
        $maxVersion = $request->post('max_version');

        Interceptor::ensureNotFalse(in_array($platform, array("android", "ios", 'server', 'cdn')), ERROR_PARAM_INVALID_FORMAT, "platform");
        Interceptor::ensureNotEmpty($name, ERROR_PARAM_IS_EMPTY, 'name');
        Interceptor::ensureNotFalse(strlen($value) > 0, ERROR_PARAM_INVALID_FORMAT, "value");
        Interceptor::ensureNotFalse(strlen($expire) > 0, ERROR_PARAM_INVALID_FORMAT, "expire");
        Interceptor::ensureNotFalse(strlen($minVersion) > 0, ERROR_PARAM_INVALID_FORMAT, "min_version");
        Interceptor::ensureNotFalse(strlen($maxVersion) > 0, ERROR_PARAM_INVALID_FORMAT, "max_version");

        $config = new ConfigService();
        $config->setConfig($name, $value, $expire, $platform, $minVersion, $maxVersion);

        return apiRender();
    }

    public function del(Request $request)
    {
        $id = $request->post('id');
        Interceptor::ensureNotFalse(strlen($id) > 0, ERROR_PARAM_INVALID_FORMAT, "id");

        $config = new ConfigService();
        $config->delConfig($id);

        return apiRender();
    }
}
