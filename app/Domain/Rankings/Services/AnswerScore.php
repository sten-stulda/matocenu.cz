<?php

namespace App\Domain\Rankings\Services;

use Illuminate\Support\Facades\DB;

/**
 * Transparent quality score of an answer (time-independent; rankings add time decay):
 *   score = Σ over unique reactors ( reaction weight × reactor trust )
 * Reactor trust: 1.0 normally; 0.5 for accounts younger than 3 days (cheap sock-puppet
 * protection); 0 for suspended/deleted accounts. Each user counts once (unique key).
 */
class AnswerScore
{
    public function compute(int $answerId): string
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT COALESCE(SUM(rt.weight * CASE
                WHEN u.status <> 'active' THEN 0
                WHEN u.created_at > now() - interval '3 days' THEN 0.5
                ELSE 1 END), 0) AS score
            FROM reactions r
            JOIN reaction_types rt ON rt.id = r.reaction_type_id
            JOIN users u ON u.id = r.user_id
            WHERE r.reactable_type = 'answer' AND r.reactable_id = ?
        SQL, [$answerId]);

        return number_format((float) $row->score, 4, '.', '');
    }
}
