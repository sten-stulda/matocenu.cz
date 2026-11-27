<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\LegalDocument;

/** Creates version 1 working drafts of legal texts if none exist (admins edit/version them later). */
class LegalDocumentsSyncer implements ReferenceDataSyncer
{
    public function sync(): string
    {
        $created = 0;
        foreach (LegalDocument::TYPES as $type => [$title]) {
            if (LegalDocument::where('type', $type)->exists()) {
                continue;
            }
            $path = database_path("data/legal/$type.md");
            LegalDocument::create([
                'type' => $type,
                'version' => 1,
                'title' => $title,
                'body' => is_file($path) ? file_get_contents($path) : '',
                'requires_consent' => in_array($type, ['terms', 'privacy'], true),
                'is_draft_for_review' => true,
                'published_at' => now(),
            ]);
            $created++;
        }

        return $created.' nových právních dokumentů';
    }
}
