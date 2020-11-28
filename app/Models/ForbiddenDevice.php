<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ForbiddenDevice extends Model
{
    protected $table = 'forbidden_device';
    protected $connection = 'passport';
    public $timestamps = false;

    public function addForbidden($deviceid, $reason)
    {
        $blockedInfo = [
            'deviceid' => $deviceid,
            'reason' => $reason,
            'addtime' => date("Y-m-d H:i:s")
        ];

        return DB::connection($this->connection)->table($this->table)->insert(
            $blockedInfo
        );
    }

    public function unForbidden($deviceid)
    {
        return DB::connection($this->connection)->table($this->table)->where('deviceid', '=', $deviceid)->delete();
    }

    public function existForbidden($deviceid)
    {
        return DB::connection($this->connection)->table($this->table)->where('deviceid', '=', $deviceid)->first();
    }
}
