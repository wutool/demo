<?php


namespace App\Http\Controllers;


use App\Util\Interceptor;
use Illuminate\Http\Request;
use App\Http\Services\ForbiddenDeviceService;

class ForbiddenDeviceController extends Controller
{
    public function forbidden(Request $request)
    {
        $deviceid = trim(strip_tags($request->post('deviceid')));
        $reason = trim(strip_tags($request->post('reason')));

        Interceptor::ensureNotFalse(strlen($deviceid), ERROR_PARAM_INVALID_FORMAT, 'deviceid');

        $forbidden = new ForbiddenDeviceService();
        $forbidden->addForbidden($deviceid, $reason);

        return apiRender();
    }

    public function unForbidden(Request $request)
    {
        $deviceid = trim(strip_tags($request->post('deviceid')));

        Interceptor::ensureNotFalse(strlen($deviceid), ERROR_PARAM_INVALID_FORMAT, 'deviceid');

        $forbidden = new ForbiddenDeviceService();
        $forbidden->unForbidden($deviceid);

        return apiRender();
    }
}
