<?php


namespace App\Jobs;

use App\Http\Services\ChannelService;
use App\Models\User;
use App\Models\UserIdentifiers;
use App\Models\OldUserLogin;

class UserRegisterChannelJob extends Job
{
    public $queue = 'user_register_channel_job';

    protected $channel;
    protected $ip;
    protected $activeTime;
    protected $platform;
    protected $deviceid;
    protected $uid;

    public function __construct($channel, $ip, $activeTime, $platform, $deviceid, $uid)
    {
        $this->channel = $channel;
        $this->ip = $ip;
        $this->activeTime = $activeTime;
        $this->platform = $platform;
        $this->deviceid = $deviceid;
        $this->uid = $uid;
    }

    public function handle()
    {
        $channel = ChannelService::getChannel(
            $this->channel,
            $this->ip,
            $this->activeTime,
            $this->platform,
            $this->deviceid
        );

        $daoUser = new User();
        $daoUser->setChannel($this->uid, $channel);
    }
}
