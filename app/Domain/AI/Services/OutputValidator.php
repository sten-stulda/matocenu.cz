<?php

namespace App\Domain\AI\Services;

use App\Domain\Moderation\Services\ContentChecker;
use App\Support\Text;

/**
 * Post-generation checks. Nothing is ever truncated (that could change the meaning) –
 * invalid variants are dropped; if none survive, the caller repairs once or offers the original.
 */
class OutputValidator
{
    private const NEGATIVE = ['ne', 'nee', 'nikdy', 'kdepak', 'vubec', 'ani', 'nic', 'zadny', 'zadna', 'nijak', 'neni', 'nemam', 'nechci', 'nebudu', 'nevim'];

    public function __construct(private ContentChecker $checker) {}

    /** Extract the JSON object even if the model wrapped it in ```json fences. @return array<string, mixed>|null */
    public function parse(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?? $raw;
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $data = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  list<mixed>  $candidates
     * @return array{valid: list<string>, problems: list<string>}
     */
    public function variants(string $original, array $candidates, int $maxLength): array
    {
        $valid = [];
        $problems = [];
        foreach ($candidates as $c) {
            if (! is_string($c)) {
                $problems[] = 'neplatná struktura';

                continue;
            }
            $t = Text::clean(trim($c, " \t\n\"'„“”"));
            $issue = $this->check($original, $t, $maxLength);
            if ($issue) {
                $problems[] = $issue;
            } elseif (! in_array($t, $valid, true)) {
                $valid[] = $t;
            }
        }

        return ['valid' => $valid, 'problems' => array_values(array_unique($problems))];
    }

    public function check(string $original, string $variant, int $maxLength): ?string
    {
        if ($variant === '') {
            return 'prázdná varianta';
        }
        if (Text::length($variant) > $maxLength) {
            return "delší než {$maxLength} znaků";
        }
        if (preg_match('/[<>{}]|variants|max_characters|style_instructions/iu', $variant)) {
            return 'obsahuje technické značky';
        }
        if (! $this->checker->check($variant)['ok']) {
            return 'zakázaný obsah (odkaz, kontakt, zakázané slovo)';
        }
        if ($this->numbers($original) !== $this->numbers($variant)) {
            return 'změněná čísla';
        }
        foreach ($this->names($original) as $name) {
            if (! str_contains(Text::fold($variant), $name)) {
                return 'chybí jméno „'.$name.'“';
            }
        }
        if (! $this->polarityKept($original, $variant)) {
            return 'změněný postoj (ano/ne)';
        }

        return null;
    }

    /** @return list<string> sorted digit groups */
    private function numbers(string $s): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', str_replace("\u{00A0}", '', $s), $m);
        $n = array_map(fn ($x) => str_replace(',', '.', $x), $m[0]);
        sort($n);

        return $n;
    }

    /** Capitalised words that are not the first word – likely names. @return list<string> */
    private function names(string $s): array
    {
        $words = preg_split('/\s+/u', trim($s)) ?: [];
        $out = [];
        foreach (array_slice($words, 1) as $w) {
            $w = trim($w, '.,!?;:"\'„“()');
            if ($w !== '' && mb_strlen($w) > 1 && mb_strtoupper(mb_substr($w, 0, 1)) === mb_substr($w, 0, 1) && preg_match('/^\p{Lu}\p{Ll}+$/u', $w)) {
                $out[] = Text::fold($w);
            }
        }

        return $out;
    }

    /** Short yes/no answers must keep their stance. */
    private function polarityKept(string $original, string $variant): bool
    {
        $o = Text::fold($original);
        $words = preg_split('/\s+/u', Text::fold($variant)) ?: [];
        $negative = (bool) array_intersect($words, self::NEGATIVE) || (bool) preg_grep('/^ne[a-z]{2,}/u', $words);
        if (in_array($o, ['ne', 'nikdy', 'urcite ne', 'vubec'], true)) {
            return $negative;
        }
        if (in_array($o, ['ano', 'jo', 'urcite', 'jasne'], true)) {
            return ! in_array($words[0] ?? '', ['ne', 'nikdy', 'kdepak'], true);
        }

        return true;
    }
}
