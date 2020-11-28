<?php


namespace App\Jobs;

use App\Http\Services\AlarmbotService;

class AlarmbotJob extends Job
{
    public $queue = 'service_alarmbot_job';

    private $type;
    private $message;

    public function __construct($type, $message)
    {
        $this->type = $type;
        $this->message = $message;
    }

    public function handle()
    {
        if ($this->type == 'service') {
            AlarmbotService::sendCustomerServiceStaffMessage($this->message);
        } else {
            AlarmbotService::sendTechnicalStaffMessage($this->message);
        }
    }
}