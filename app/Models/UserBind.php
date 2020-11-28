<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserBind extends Model
{
    protected $connection = 'passport';
    protected $table = 'userbind';

    public $timestamps = false;

    public function addUserBind($uid, $rid, $source, $nickname, $avatar, $token)
    {
        $bindinfo = array(
            "uid" => $uid,
            "rid" => $rid,
            "source" => $source,
            "access_token" => $token,
            "nickname" => $nickname,
            "avatar" => $avatar,
            "addtime" => date("Y-m-d H:i:s"),
            "modtime" => date("Y-m-d H:i:s")
        );

        return DB::connection($this->connection)->table($this->table)->insert(
            $bindinfo
        );
    }

    public function setUserBind($uid, $source, $rid)
    {
        $bindinfo = array(
            "rid" => $rid,
            "modtime" => date("Y-m-d H:i:s")
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('uid', $uid)
            ->where('source', $source)
            ->update($bindinfo);
    }

    public function getUserBind($uid, $source)
    {
        $result = [];
        $fields = $this->getFields();
        $bindInfo = DB::connection($this->connection)->table($this->table)->select(
            $fields
        )->where('uid', '=', $uid)
            ->where('source', '=', $source)
            ->first();

        if ($bindInfo) {
            $result = (array)$bindInfo;
        }

        return $result;
    }

    public function getUserBindBySource($rid, $source)
    {
        $result = [];
        $fields = $this->getFields();
        $bindInfo = DB::connection($this->connection)->table($this->table)->select(
            $fields
        )->where('rid', '=', $rid)
            ->where('source', '=', $source)
            ->first();

        if ($bindInfo) {
            $result = (array)$bindInfo;
        }

        return $result;
    }

    public function getUserBinds($uid)
    {
        $fields = $this->getFields();
        $bindInfo = DB::connection($this->connection)->table($this->table)->select(
            $fields
        )->where('uid', '=', $uid)->get()->toArray();

        return $bindInfo;
    }

    public function deleteUserBind($uid, $source)
    {
        return DB::connection($this->connection)->table($this->table)
            ->where('uid', '=', $uid)
            ->where('source', '=', $source)
            ->delete();
    }

    private function getFields()
    {
        return ['id', 'uid', 'rid', 'nickname', 'avatar', 'source', 'addtime', 'modtime'];
    }
}