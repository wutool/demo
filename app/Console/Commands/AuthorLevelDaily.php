<?php


namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\Models\Anchor;
use App\Models\AnchorLevelDaily;
use App\Jobs\AlarmbotJob;

class AuthorLevelDaily extends Command
{
    protected $signature = 'anchor:levelDaily';

    protected $description = '每日评级';

    public function handle()
    {
        $this->info("AnchorLevelDaily start:" . date('Y-m-d H:i:s'));

        $anchor = new Anchor();
        $levelDaily = new AnchorLevelDaily();
        $anchorData = $anchor->getAnchor();

        $day = date('Y-m-d', strtotime('-1 days'));
        $dayBegin = $day . ' 00:00:00';
        $dayEnd = $day . ' 23:59:59';

        # 删除原有数据
        AnchorLevelDaily::where('day', '=', $day)->delete();

        foreach ($anchorData as $value) {
            # 计算收入
            $score = (int)$this->score($value['user_id'], $dayBegin, $dayEnd);

            # 计算评级
            $levelArr = $this->level($value['content_type'], $score);
            foreach ($levelArr as $level) {
                $levelDaily->add(
                    $value['user_id'],
                    (int)$value['family_id'],
                    $value['content_type'],
                    $level,
                    $score,
                    $day
                );
            }
            unset($level);
        }
        unset($value);

        $botMessage = "[提醒][评级] {$day}, 执行完成";
        dispatch(new AlarmbotJob('service', $botMessage));

        $this->info("AnchorLevelDaily end:" . date('Y-m-d H:i:s'));
    }

    private function score($uid, $dayBegin, $dayEnd)
    {
        return DB::connection('old')->table('qvod_zb_t_consume_logs')
            ->where('rec_uid', $uid)
            ->whereIn('type', [1, 2, 3, 11])
            ->where('created_at', '>=', $dayBegin)
            ->where('created_at', '<=', $dayEnd)
            ->sum('points');
    }

    private function level($contentType, $score)
    {
        if ($contentType == 1) {
            $level = [
                ['level' => 0, 'min' => 0, 'max' => 2999],
                ['level' => 1, 'min' => 3000, 'max' => 6000],
                ['level' => 2, 'min' => 6001, 'max' => 12000],
                ['level' => 3, 'min' => 12000, 'max' => 30000],
                ['level' => 4, 'min' => 30001, 'max' => PHP_INT_MAX],
            ];
        } else {
            $level = [
                ['level' => 0, 'min' => 0, 'max' => 5999],
                ['level' => 1, 'min' => 6001, 'max' => 15000],
                ['level' => 2, 'min' => 15001, 'max' => 30000],
                ['level' => 3, 'min' => 30001, 'max' => 50001],
                ['level' => 4, 'min' => 50001, 'max' => PHP_INT_MAX],
            ];
        }

        $result = [];
        foreach ($level as $value) {
            $result[] = $value['level'];
            if (($score >= $value['min']) && ($score < $value['max'])) {
                return $result;
            }
        }
    }

}