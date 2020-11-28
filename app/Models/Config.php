<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Config extends Model
{
    protected $table = 'config';
    protected $connection = 'live';
    public $timestamps = false;

    public function getConfigById($id)
    {
        $data = DB::connection($this->connection)->table($this->table)
            ->where('id', $id)
            ->first();

        if ($data) {
            $data = (array)$data;
            $data['value'] = json_decode($data['value'], true);
        }

        return $data;
    }

    public function getConfig($name, $platform, $minVersion, $maxVersion)
    {
        // 取最小版本要求最接近的 order by min_version desc
        $sql = 'select * from ' . $this->table . ' where `name` = ? and `platform` = ? and min_version <= ? and (max_version >= ? or max_version = 0) order by min_version desc, id desc limit 1';

        $data = DB::select($sql, array(
            $name,
            $platform,
            $minVersion,
            $maxVersion
        ));

        if ($data) {
            $data = current($data);
            $data = (array)$data;
            $data['value'] = json_decode($data['value'], true);
        }

        return $data;
    }

    public function getConfigs(array $names, $platform, $minVersion, $maxVersion)
    {
        $values = array_merge($names, array(
            $platform,
            $minVersion,
            $maxVersion
        ));

        $placeholder = implode(',', array_fill(0, count($names), '?'));

        // 取最小版本要求最接近的 order by minversion desc
        $sql = 'select * from ' . $this->table . ' where `name` in (' . $placeholder . ') and `platform` = ? and min_version <= ? and (max_version >= ? or max_version = 0) order by min_version asc, id asc';
        $datas = DB::connection($this->connection)->select($sql, $values);

        $result = array();
        foreach ($datas as $data) {
            $item = (array)$data;
            $item['value'] = json_decode($data->value, true);
            $result[$data->name] = $item;
        }

        return $result;
    }

    public function setConfig($name, $value, $expire, $platform, $minVersion, $maxVersion)
    {
        $info = array(
            'region' => 'china',
            'name' => $name,
            'platform' => $platform,
            'min_version' => $minVersion,
            'max_version' => $maxVersion,
            'value' => json_encode($value),
            'expire' => $expire,
            'addtime' => date('Y-m-d H:i:s'),
            'modtime' => date('Y-m-d H:i:s')
        );

        $fields = array_keys($info);

        $sql = "replace into {$this->table} (`" . implode("`,`", $fields) . "`) values (" . str_repeat("?,", count($fields) - 1) . "?)";

        return DB::connection($this->connection)->insert($sql, array_values($info));
    }

    public function delConfig($id)
    {
        return DB::connection($this->connection)->table($this->table)->where('id', '=', $id)->delete();
    }
}