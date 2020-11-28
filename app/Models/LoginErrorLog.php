<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Util\Util;
use App\Util\Context;
use Illuminate\Support\Facades\DB;

class LoginErrorLog extends Model
{
    protected $table = 'errorlog';
    protected $connection = 'passport';
    public $timestamps = false;

    public function addLog($uid = null, $mobile = null, $errno = "", $extend = array())
    {
        $info["uid"] = intval($uid);
        $info["mobile"] = strval($mobile);
        $info["ip"] = strval(Util::getIP());
        $info["deviceid"] = strval(Context::get("deviceid"));
        $info["platform"] = strval(Context::get("platform"));
        $info["version"] = strval(Context::get("version"));
        $info["netspeed"] = strval(Context::get("netspeed"));
        $info["network"] = strval(Context::get("network"));
        $info["model"] = strval(Context::get("model"));
        $info["channel"] = strval(Context::get("channel"));
        $info["errno"] = strval($errno);
        $info["extend"] = $extend ? json_encode($extend) : "";
        $info["addtime"] = date("Y-m-d H:i:s");

        return DB::connection($this->connection)->table($this->getTable())->insert(
            $info
        );
    }

    public function getTable()
    {
        return $this->table . "_" . date("Ym");
    }
}