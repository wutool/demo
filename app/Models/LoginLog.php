<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoginLog extends Model
{
    protected $table = 'loginlog';
    protected $connection = 'passport';
    public $timestamps = false;

    public function addLoginLog($uid, $ip, $deviceid, $platform, $version, $brand, $model, $network, $netspeed, $extend)
    {
        $logInfo = array(
            "uid" => $uid,
            "ip" => $ip,
            "deviceid" => $deviceid,
            "platform" => $platform,
            "version" => $version,
            "brand" => $brand,
            "model" => $model,
            "network" => $network,
            "netspeed" => $netspeed,
            "addtime" => date("Y-m-d H:i:s"),
            "extend" => $extend
        );

        return DB::connection($this->connection)->table($this->table . "_" . date("Ym"))->insert(
            $logInfo
        );
    }
}