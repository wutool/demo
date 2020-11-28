<?php
/**
 * 排行榜
 */

namespace App\Jobs;

use App\Http\Services\RankService;
use Exception;
use Illuminate\Support\Facades\Log;

class RankingGenerateJob extends Job
{
    public $queue = 'ranking_generate_job';

    protected $type;
    protected $action;
    protected $userid;
    protected $score;
    protected $relateid;
    protected $liveid;
    protected $planid;

    public function __construct($type, $action, $userid, $score, $relateid = 0, $liveid = 0, $planid = 0)
    {
        $this->type = $type;
        $this->action = $action;
        $this->userid = $userid;
        $this->score = $score;
        $this->relateid = $relateid;
        $this->liveid = $liveid;
        $this->planid = $planid;
    }

    public function handle()
    {
        try {
            $rank = new RankService();
            $rank->setRank($this->type, $this->action, $this->userid, $this->score, $this->relateid, $this->liveid, $this->planid);
        } catch (Exception $e) {
            Log::error(json_encode([
                'job' => __CLASS__,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

    }

}
