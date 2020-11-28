<?php


namespace App\Http\Services;


use App\Models\ShortlinkNotice;

class ChannelService
{
    public static function getChannel($channel, $ip, $activeTiime, $platform, $deviceid)
    {
        $platform = strtolower(trim($platform));
        $effectiveTime = date('Y-m-d H:i:s', strtotime($activeTiime) - 3 * 60 * 60);

        if ($platform == 'ios') {
            $channel = self::platformIos($deviceid, $ip, $effectiveTime);
        } else {
            $channel = self::platformAndroid($channel, $ip, $effectiveTime, $deviceid);
        }

        return $channel;
    }

    private static function platformIos($deviceid, $ip, $effectiveTime)
    {
        $channel = 'server';

        $ip = ip2long($ip);
        $data = ShortlinkNotice::select(['id', 'channel'])
            ->where('link_ip', '=', $ip)
            ->where('link_time', '>', $effectiveTime)
            ->where('deviceid', '')
            ->orderBy('id', 'desc')
            ->first();

        if ($data) {
            $channel = $data->channel;
            ShortlinkNotice::where('id', $data->id)
                ->update([
                    'deviceid' => $deviceid,
                    'activetime' => date('Y-m-d H:i:s'),
                ]);
        }

        return $channel;
    }

    private static function platformAndroid($channel, $ip, $effective_time, $deviceid)
    {
        return $channel;
    }
}