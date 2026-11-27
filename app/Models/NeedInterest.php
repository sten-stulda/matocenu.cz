<?php

namespace App\Models;

use App\Domain\Needs\Enums\Frequency;
use App\Domain\Needs\Enums\InterestKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property InterestKind $kind
 * @property string|null $amount
 * @property Frequency|null $frequency
 * @property bool $contact_consent
 */
class NeedInterest extends Model
{
    protected $fillable = ['need_id', 'user_id', 'kind', 'amount', 'currency', 'frequency', 'location_id', 'urgency', 'is_commitment', 'contact_consent'];

    protected function casts(): array
    {
        return ['kind' => InterestKind::class, 'frequency' => Frequency::class, 'amount' => 'decimal:2', 'is_commitment' => 'boolean', 'contact_consent' => 'boolean'];
    }

    /** @return BelongsTo<Need, $this> */
    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }
}
