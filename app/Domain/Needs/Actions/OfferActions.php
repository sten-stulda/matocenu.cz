<?php

namespace App\Domain\Needs\Actions;

use App\Domain\Gamification\Services\Achievements;
use App\Domain\Moderation\Services\ContentChecker;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Needs\Enums\InterestKind;
use App\Domain\Needs\Exceptions\NeedException;
use App\Domain\Needs\Services\NeedCounters;
use App\Models\Need;
use App\Models\NeedInterest;
use App\Models\NeedMessage;
use App\Models\NeedOffer;
use App\Models\User;
use App\Notifications\NeedOfferMessage;
use App\Notifications\NeedOfferReceived;
use App\Support\Text;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Providers offer solutions publicly. Contacting interested people is possible ONLY for those who
 * gave explicit consent, and only as a relayed platform message – no e-mails or names are revealed.
 */
class OfferActions
{
    public const BROADCAST_COOLDOWN_DAYS = 7;

    public function __construct(private Moderation $moderation, private NeedCounters $counters, private InterestActions $interests) {}

    public function create(User $provider, Need $need, string $description, ?string $price): NeedOffer
    {
        if ($need->user_id === $provider->id) {
            throw new NeedException('Vlastní potřebě řešení nabízet nemůžeš – změň její stav na „Řeší se“.');
        }
        if (! $provider->isActive() || ! $provider->hasVerifiedEmail()) {
            throw new NeedException('Nabídku může dát jen uživatel s ověřeným e-mailem.');
        }
        if (! $need->isPubliclyVisible() || in_array($need->status, ['solved', 'closed'], true)) {
            throw new NeedException('Tato potřeba už nabídky nepřijímá.');
        }
        $description = Text::clean($description, multiline: true);
        if (Text::length($description) < 15) {
            throw new NeedException('Popiš nabídku aspoň pár slovy (min. 15 znaků).');
        }
        try {
            $offer = DB::transaction(function () use ($provider, $need, $description, $price) {
                [$status, $check] = $this->moderation->initialStatus($description.' '.$price);
                $o = new NeedOffer(['description' => $description, 'price_text' => $price ? mb_substr(Text::clean($price), 0, 120) : null]);
                $o->need_id = $need->id;
                $o->user_id = $provider->id;
                $o->moderation_status = $status;
                $o->save();
                if (! $check['ok']) {
                    $this->moderation->recordAutoFlag($o, $check);
                }

                return $o;
            });
        } catch (UniqueConstraintViolationException) {
            throw new NeedException('K této potřebě už nabídku máš – můžeš ji upravit.');
        }
        // The provider also counts as "can deliver".
        $this->interests->set($provider, $need, InterestKind::CanDeliver);
        $this->counters->need($need->id);
        if ($offer->moderation_status->isPublic() && $need->user->isActive()) {
            $need->user->notify(new NeedOfferReceived($offer));
        }

        return $offer;
    }

    public function decide(User $author, NeedOffer $offer, bool $accept): void
    {
        if ($offer->need->user_id !== $author->id) {
            throw new NeedException('O nabídce rozhoduje autor potřeby.');
        }
        $offer->forceFill(['status' => $accept ? 'accepted' : 'declined'])->save();
        if ($accept && $offer->need->status === 'open') {
            app(NeedActions::class)->changeStatus($author, $offer->need, 'in_progress', 'Přijata nabídka realizátora');
        }
        if ($accept) {
            app(Achievements::class)->evaluate($offer->user);
        }
    }

    public function withdraw(User $provider, NeedOffer $offer): void
    {
        if ($offer->user_id !== $provider->id) {
            throw new NeedException('Stáhnout můžeš jen vlastní nabídku.');
        }
        $offer->forceFill(['status' => 'withdrawn'])->save();
        $this->counters->need($offer->need_id);
    }

    /** Relay a message to all consenting interested people. @return int recipients */
    public function broadcast(User $provider, NeedOffer $offer, string $body): int
    {
        if ($offer->user_id !== $provider->id) {
            throw new NeedException('Zprávu může poslat jen autor nabídky.');
        }
        if (! $offer->moderation_status->isPublic() || $offer->status === 'withdrawn') {
            throw new NeedException('Nabídka zatím není zveřejněná.');
        }
        if ($offer->last_broadcast_at && $offer->last_broadcast_at->gt(now()->subDays(self::BROADCAST_COOLDOWN_DAYS))) {
            throw new NeedException('Zájemcům můžeš napsat nejvýše jednou za '.self::BROADCAST_COOLDOWN_DAYS.' dní.');
        }
        $body = Text::clean($body, multiline: true);
        if (Text::length($body) < 10 || Text::length($body) > 1000) {
            throw new NeedException('Zpráva musí mít 10–1000 znaků.');
        }
        if (! app(ContentChecker::class)->check($body)['ok']) {
            throw new NeedException('Zpráva nesmí obsahovat odkazy ani kontakty – domluvte se přes platformu.');
        }
        $recipients = User::whereIn('id', NeedInterest::where('need_id', $offer->need_id)->where('contact_consent', true)
            ->where('user_id', '!=', $provider->id)->select('user_id'))->where('status', 'active')->get();
        DB::transaction(function () use ($provider, $offer, $body, $recipients) {
            NeedMessage::create(['need_offer_id' => $offer->id, 'sender_id' => $provider->id, 'body' => $body, 'recipients_count' => $recipients->count()]);
            $offer->forceFill(['last_broadcast_at' => now()])->save();
        });
        foreach ($recipients as $r) {
            $r->notify(new NeedOfferMessage($offer, $body));
        }

        return $recipients->count();
    }
}
