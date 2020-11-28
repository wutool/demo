<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Forbidden extends Model
{
    protected $table = 'forbidden';
    protected $connection = 'passport';
    public $timestamps = false;

    public function addForbidden($uid, $expire, $reason = "")
    {
        $arrInfo["uid"] = $uid;
        $arrInfo["expire"] = $expire;
        $arrInfo["reason"] = $reason;
        $arrInfo["addtime"] = $arrInfo['modtime'] = date("Y-m-d H:i:s");

        return DB::connection($this->connection)->table($this->table)->insert(
            $arrInfo
        );
    }

    public function unForbidden($uid)
    {
        return DB::connection($this->connection)->table($this->table)->where('uid', '=', $uid)->delete();
    }

    public function getForbiddenLists($uids)
    {
        return DB::connection($this->connection)->table($this->table)->select('uid as relateid', 'expire', 'reason')->whereIn('uid', $uids)->get()->toArray();
    }

    public function getForbidden($uid)
    {
        $result = DB::connection($this->connection)->table($this->table)->select('uid as relateid', 'expire', 'reason')->where('uid', '=', $uid)->first();

        return (array)$result;
    }
}