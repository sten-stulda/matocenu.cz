<?php

namespace App\Support;

use Normalizer;

/** Unicode-safe text handling for user content (Czech diacritics, emoji). */
final class Text
{
    /** NFC normalisation, strip control/zero-width chars, collapse whitespace, trim. */
    public static function clean(?string $s, bool $multiline = false): string
    {
        $s = (string) $s;
        if (! mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;
        // Remove control characters except newline/tab, plus zero-width & bidi overrides.
        $s = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $s) ?? '';
        if ($multiline) {
            $s = preg_replace("/[ \t]+/u", ' ', $s) ?? '';
            $s = preg_replace("/\n{3,}/u", "\n\n", str_replace("\r", '', $s)) ?? '';
        } else {
            $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        }

        return trim($s);
    }

    /** User-perceived length (grapheme clusters) – "100 znaků včetně mezer". */
    public static function length(string $s): int
    {
        if (function_exists('grapheme_strlen')) {
            $n = grapheme_strlen($s);
            if (is_int($n)) {
                return $n;
            }
        }

        return mb_strlen($s);
    }

    /** Lower-case, diacritics-free form for comparisons and cache keys. */
    public static function fold(string $s): string
    {
        $s = mb_strtolower(self::clean($s));
        $t = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');

        return trim(preg_replace('/[^\p{L}\p{N} ]+/u', '', $t ? (string) $t->transliterate($s) : $s) ?? '');
    }
}
