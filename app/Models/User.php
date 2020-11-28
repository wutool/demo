<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    protected $connection = 'old';
    protected $table = 'qvod_uc_users';

    public $timestamps = false;

    public function findByUserName($username)
    {
        $result = DB::connection($this->connection)->table($this->table)
            ->select(['id as uid', 'password', 'status', 'salt'])
            ->where('username', $username)->first();

        return $result;
    }

    public function getUserInfo($uid)
    {
        $userInfo = DB::connection($this->connection)->table($this->table)->select('id as uid',
            'username',
            'nickname',
            'sex',
            'birthday',
            'signature',
            'avatar',
            'constellation',
            'sex',
            'salt',
            'channel',
            'exp',
            'level',
            'vip',
            'car_id',
            'location',
            'role'
        )->where('id', '=', $uid)->first();

        return (array)$userInfo;
    }

    public function register($username, $nickname, $avatar, $platform, $channel, $addtime, $modtime)
    {
        $arrInfo = [
            'username' => $username,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'os' => $platform,
            'channel' => $channel,
            'created_at' => $addtime,
            'updated_at' => $modtime,
        ];

        return DB::connection($this->connection)->table($this->table)->insertGetId(
            $arrInfo
        );
    }

    public function setPassword($uid, $password, $salt)
    {
        $userinfo = array(
            "password" => $password,
            "salt" => $salt,
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('id', $uid)
            ->update($userinfo);
    }

    public function setChannel($uid, $channel)
    {
        $userinfo = array(
            'channel' => $channel
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('id', $uid)
            ->update($userinfo);
    }

    public function setRide($uid, $rideid, $expiretime)
    {
        $userinfo = array(
            'car_id' => $rideid,
            'ex_car_time' => $expiretime,
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('id', $uid)
            ->update($userinfo);
    }

    public function setRole($uid, $role)
    {
        $userinfo = array(
            'role' => $role,
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('id', $uid)
            ->update($userinfo);
    }

    public function setVip($uid, $vip_id, $exTime)
    {
        $userinfo = array(
            'vip'   => $vip_id,
            'ex_vip_time' => $exTime
        );

        return DB::connection($this->connection)->table($this->table)
            ->where('id', $uid)
            ->update($userinfo);
    }
}
