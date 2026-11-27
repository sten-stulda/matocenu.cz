<?php

namespace App\Domain\Notifications;

use App\Models\User;

/** Catalogue of user-facing notification types and their channels/defaults. */
final class NotificationTypes
{
    /** @return array<string, array{label: string, channels: list<string>, default: array<string, bool>}> */
    public static function all(): array
    {
        return [
            'daily_question' => ['label' => 'Nová otázka dne', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'answer_reaction' => ['label' => 'Reakce na mou odpověď (souhrn)', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'comment' => ['label' => 'Komentář k mému příspěvku', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'question_answered' => ['label' => 'Odpověď na mou otázku', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'achievement' => ['label' => 'Získané ocenění', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'need_offer' => ['label' => 'Nabídka řešení mé potřeby', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => true]],
            'followed_topic' => ['label' => 'Změna sledovaného tématu', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'quote_results' => ['label' => 'Vyhodnocení hlášky měsíce', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => false]],
            'moderation' => ['label' => 'Rozhodnutí moderace o mém obsahu', 'channels' => ['database', 'mail'], 'default' => ['database' => true, 'mail' => true]],
        ];
    }

    /** @return list<string> channels the user wants for $type */
    public static function channelsFor(User $user, string $type): array
    {
        $def = self::all()[$type] ?? null;
        if (! $def || ! $user->isActive()) {
            return [];
        }
        $prefs = $user->preferences();
        $out = [];
        foreach ($def['channels'] as $ch) {
            if ($prefs->wantsNotification($type, $ch, $def['default'][$ch] ?? true)) {
                // E-mail only to verified addresses.
                if ($ch === 'mail' && ! $user->hasVerifiedEmail()) {
                    continue;
                }
                $out[] = $ch;
            }
        }

        return $out;
    }
}
