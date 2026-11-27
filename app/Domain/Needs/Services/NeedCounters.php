<?php

namespace App\Domain\Needs\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Needs\Enums\InterestKind;
use Illuminate\Support\Facades\DB;

class NeedCounters
{
    public function need(int $needId): void
    {
        $counts = DB::table('need_interests')->where('need_id', $needId)->groupBy('kind')->selectRaw('kind, count(*) as n')->pluck('n', 'kind');
        $data = [
            'interested_count' => DB::table('need_interests')->where('need_id', $needId)->distinct()->count('user_id'),
            'offers_count' => DB::table('need_offers')->where('need_id', $needId)->whereNull('deleted_at')->where('status', '!=', 'withdrawn')
                ->whereIn('moderation_status', ModerationStatus::publicValues())->count(),
            'last_activity_at' => now(),
        ];
        foreach (InterestKind::cases() as $k) {
            $data[$k->counter()] = (int) ($counts[$k->value] ?? 0);
        }
        DB::table('needs')->where('id', $needId)->update($data);
    }
}
