<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Administration\Services\Settings;
use App\Support\Text;

/**
 * Cheap automatic checks run before content is published. Flagged content goes to the
 * moderation queue (under_review) instead of being published; nothing is silently deleted.
 */
class ContentChecker
{
    public function __construct(private Settings $settings) {}

    /** @return array{ok: bool, flags: list<string>, risk: string|null, reason: string|null} */
    public function check(string $text): array
    {
        $flags = [];
        $folded = Text::fold($text);

        if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(cz|com|sk|eu|net|org|info|shop|xyz)\b)~iu', $text)) {
            $flags['link'] = 'Odpověď obsahuje odkaz';
        }
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $text)) {
            $flags['email'] = 'Obsahuje e-mailovou adresu (osobní údaje)';
        }
        if (preg_match('/(?:\+?420[ -]?)?\b\d{3}[ -]?\d{3}[ -]?\d{3}\b/u', $text)) {
            $flags['phone'] = 'Obsahuje telefonní číslo (osobní údaje)';
        }
        foreach ($this->settings->list('moderation.blocked_words') as $word) {
            $w = Text::fold($word);
            if ($w !== '' && preg_match('/(^|\s)'.preg_quote($w, '/').'(\s|$)/u', $folded)) {
                $flags['blocked'] = 'Obsahuje zakázané slovo';
                break;
            }
        }
        if (preg_match('/(.)\1{9,}/u', $text)) {
            $flags['repeat'] = 'Opakované znaky (spam)';
        }

        $risk = isset($flags['email']) || isset($flags['phone']) || isset($flags['blocked']) ? 'high' : ($flags ? 'medium' : null);

        return ['ok' => $flags === [], 'flags' => array_keys($flags), 'risk' => $risk, 'reason' => $flags ? implode('; ', $flags) : null];
    }

    /** Friendly hint shown to the author before submitting (design: „Odpověď obsahuje odkaz – ten sem nepatří“). */
    public function hint(string $text): ?string
    {
        $r = $this->check($text);
        if ($r['ok']) {
            return null;
        }

        return match ($r['flags'][0]) {
            'link' => 'Odpověď obsahuje odkaz – ten sem nepatří. Zkus to bez odkazu.',
            'email', 'phone' => 'Vypadá to na osobní údaje. Ty sem prosím nepiš.',
            'repeat' => 'Tolik stejných znaků za sebou? Zkus to říct po svém.',
            default => 'Tohle by neprošlo pravidly komunity. Zkus to jinak.',
        };
    }
}
