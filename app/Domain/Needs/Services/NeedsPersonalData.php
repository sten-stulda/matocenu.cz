<?php

namespace App\Domain\Needs\Services;

use App\Domain\Identity\Contracts\PersonalDataContributor;
use App\Models\Need;
use App\Models\NeedInterest;
use App\Models\NeedMessage;
use App\Models\NeedOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NeedsPersonalData implements PersonalDataContributor
{
    public function key(): string
    {
        return 'potreby';
    }

    public function export(User $user): array
    {
        return [
            'moje_potreby' => Need::withTrashed()->where('user_id', $user->id)->get(['title', 'description', 'status', 'is_anonymous', 'created_at'])->toArray(),
            'zajem' => NeedInterest::with('need:id,title')->where('user_id', $user->id)->get()->map(fn ($i) => [
                'potreba' => $i->need?->title, 'typ' => $i->kind->label(), 'castka' => $i->amount, 'mena' => $i->currency,
                'frekvence' => $i->frequency?->label(), 'zavazne' => $i->is_commitment, 'souhlas_s_kontaktem' => $i->contact_consent,
            ])->all(),
            'nabidky' => NeedOffer::withTrashed()->where('user_id', $user->id)->get(['description', 'price_text', 'status', 'created_at'])->toArray(),
            'odeslane_zpravy' => NeedMessage::where('sender_id', $user->id)->get(['body', 'recipients_count', 'created_at'])->toArray(),
        ];
    }

    public function erase(User $user, bool $deleteContent): void
    {
        $needIds = NeedInterest::where('user_id', $user->id)->pluck('need_id')->merge(NeedOffer::where('user_id', $user->id)->pluck('need_id'))->unique();
        NeedInterest::where('user_id', $user->id)->delete();
        if ($deleteContent) {
            Need::where('user_id', $user->id)->get()->each->delete();
            NeedOffer::where('user_id', $user->id)->get()->each->delete();
        } else {
            Need::withTrashed()->where('user_id', $user->id)->update(['is_anonymous' => true]);
        }
        DB::table('need_messages')->where('sender_id', $user->id)->update(['body' => '[smazáno]']);
        foreach ($needIds as $id) {
            app(NeedCounters::class)->need((int) $id);
        }
    }
}
