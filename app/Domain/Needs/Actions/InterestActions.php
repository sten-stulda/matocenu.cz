<?php

namespace App\Domain\Needs\Actions;

use App\Domain\Needs\Enums\Frequency;
use App\Domain\Needs\Enums\InterestKind;
use App\Domain\Needs\Exceptions\NeedException;
use App\Domain\Needs\Services\NeedCounters;
use App\Models\Need;
use App\Models\NeedInterest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One row per (need, user, kind) – unique key, so repeated clicks never inflate demand.
 * Declared willingness to pay is non-binding; "is_commitment" is a separate, explicit flag.
 */
class InterestActions
{
    public function __construct(private NeedCounters $counters) {}

    /** @param array{amount?: string|null, currency?: string|null, frequency?: string|null, is_commitment?: bool, contact_consent?: bool, urgency?: string|null} $details */
    public function set(User $user, Need $need, InterestKind $kind, array $details = []): NeedInterest
    {
        $this->guard($user, $need);
        $amount = $details['amount'] ?? null;
        if ($amount !== null && $amount !== '') {
            $amount = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $amount);
            if (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > 1_000_000) {
                throw new NeedException('Částka musí být číslo mezi 0 a 1 000 000.');
            }
            $amount = number_format((float) $amount, 2, '.', '');
        } else {
            $amount = null;
        }
        $frequency = ($details['frequency'] ?? null) ? Frequency::tryFrom((string) $details['frequency']) : null;
        $currency = in_array($details['currency'] ?? 'CZK', ['CZK', 'EUR'], true) ? ($details['currency'] ?? 'CZK') : 'CZK';

        $interest = DB::transaction(function () use ($user, $need, $kind, $amount, $frequency, $currency, $details) {
            DB::table('need_interests')->insertOrIgnore(['need_id' => $need->id, 'user_id' => $user->id, 'kind' => $kind->value, 'created_at' => now(), 'updated_at' => now()]);
            $row = NeedInterest::where(['need_id' => $need->id, 'user_id' => $user->id, 'kind' => $kind->value])->lockForUpdate()->firstOrFail();
            $row->fill([
                'amount' => $kind === InterestKind::WouldPay ? $amount : null,
                'currency' => $kind === InterestKind::WouldPay && $amount !== null ? $currency : null,
                'frequency' => $kind === InterestKind::WouldPay ? $frequency : null,
                'location_id' => $user->location_id,
                'urgency' => in_array($details['urgency'] ?? null, ['low', 'medium', 'high'], true) ? $details['urgency'] : null,
                'is_commitment' => (bool) ($details['is_commitment'] ?? false),
                'contact_consent' => (bool) ($details['contact_consent'] ?? false),
            ])->save();

            return $row;
        });
        $this->counters->need($need->id);

        return $interest;
    }

    public function remove(User $user, Need $need, InterestKind $kind): void
    {
        NeedInterest::where(['need_id' => $need->id, 'user_id' => $user->id, 'kind' => $kind->value])->delete();
        $this->counters->need($need->id);
    }

    /** Withdraw consent to be contacted by providers for all of this user's interests in the need. */
    public function setConsent(User $user, Need $need, bool $consent): void
    {
        NeedInterest::where(['need_id' => $need->id, 'user_id' => $user->id])->update(['contact_consent' => $consent]);
    }

    private function guard(User $user, Need $need): void
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            throw new NeedException('Zájem mohou projevit uživatelé s ověřeným e-mailem.');
        }
        if ($need->user_id === $user->id) {
            throw new NeedException('U vlastní potřeby se zájem nepočítá – jsi přece autor.');
        }
        if (! $need->isPubliclyVisible() || in_array($need->status, ['closed'], true)) {
            throw new NeedException('Tato potřeba už zájem nesbírá.');
        }
    }
}
