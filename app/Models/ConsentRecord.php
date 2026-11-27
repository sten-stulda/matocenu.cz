<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'terms' => 'Podmínky používání',
        'privacy' => 'Zásady ochrany osobních údajů',
        'ai_processing' => 'Stylizace odpovědí pomocí AI',
        'marketing' => 'Marketingové e-maily',
        'provider_contact' => 'Kontakt od realizátorů potřeb',
        'original_public' => 'Zveřejnění originálu odpovědí',
    ];

    protected $fillable = ['user_id', 'legal_document_id', 'consent_type', 'granted', 'source'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LegalDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
