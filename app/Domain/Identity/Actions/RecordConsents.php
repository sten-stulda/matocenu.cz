<?php

namespace App\Domain\Identity\Actions;

use App\Models\ConsentRecord;
use App\Models\LegalDocument;
use App\Models\User;

class RecordConsents
{
    public function registration(User $user, bool $marketing, string $source = 'registration'): void
    {
        $this->grant($user, 'terms', true, $source);
        $this->grant($user, 'privacy', true, $source);
        if ($marketing) {
            $this->grant($user, 'marketing', true, $source);
            $user->preferences()->update(['marketing_emails' => true]);
        }
    }

    public function grant(User $user, string $type, bool $granted, string $source = 'web'): ConsentRecord
    {
        $doc = in_array($type, ['terms', 'privacy'], true) ? LegalDocument::current($type) : null;

        return ConsentRecord::create([
            'user_id' => $user->id,
            'legal_document_id' => $doc?->id,
            'consent_type' => $type,
            'granted' => $granted,
            'source' => $source,
        ]);
    }

    /** Has the user accepted the currently published version of every document that requires consent? */
    public function upToDate(User $user): bool
    {
        foreach (['terms', 'privacy'] as $type) {
            $doc = LegalDocument::current($type);
            if (! $doc || ! $doc->requires_consent) {
                continue;
            }
            $ok = ConsentRecord::where('user_id', $user->id)->where('consent_type', $type)
                ->where('legal_document_id', $doc->id)->where('granted', true)->exists();
            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
