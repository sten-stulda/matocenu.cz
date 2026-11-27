<?php

namespace App\Domain\Reactions\Services;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Two queries for a whole list of items instead of two per item (avoids N+1 in answer lists). */
class ReactionSummary
{
    /**
     * @param  iterable<int, Model>  $items
     * @return array<int, array{counts: array<string, int>, mine: ?string}>
     */
    public function for(string $type, iterable $items, ?User $viewer): array
    {
        $ids = collect($items)->pluck('id')->all();
        $out = array_fill_keys($ids, ['counts' => [], 'mine' => null]);
        if (! $ids) {
            return $out;
        }
        $base = fn () => Reaction::query()->join('reaction_types', 'reaction_types.id', '=', 'reactions.reaction_type_id')
            ->where('reactable_type', $type)->whereIn('reactable_id', $ids);

        foreach ($base()->selectRaw('reactable_id, reaction_types.slug, count(*) as n')->groupBy('reactable_id', 'reaction_types.slug')->toBase()->get() as $row) {
            $out[$row->reactable_id]['counts'][$row->slug] = (int) $row->n;
        }
        if ($viewer) {
            foreach ($base()->where('user_id', $viewer->id)->toBase()->get(['reactable_id', 'reaction_types.slug']) as $row) {
                $out[$row->reactable_id]['mine'] = $row->slug;
            }
        }

        return $out;
    }
}
