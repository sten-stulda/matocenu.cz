<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $period
 * @property string $period_key
 * @property string $display_text
 * @property string $original_text
 * @property bool $is_stylized
 * @property string $legal_status
 * @property string $license_status
 * @property string $commercial_status
 * @property Answer $answer
 */
class Quote extends Model
{
    public const PERIODS = ['day' => 'Hláška dne', 'week' => 'Hláška týdne', 'month' => 'Hláška měsíce'];

    public const LEGAL = ['unchecked' => 'Neprověřeno', 'in_review' => 'V prověřování', 'cleared' => 'Prověřeno', 'rejected' => 'Nepoužitelné'];

    public const LICENSE = ['none' => 'Bez licence', 'requested' => 'Vyžádána', 'granted' => 'Udělena', 'refused' => 'Odmítnuta'];

    public const COMMERCIAL = ['not_approved' => 'Neschváleno', 'approved' => 'Schváleno'];

    protected $fillable = ['answer_id', 'period', 'period_key', 'original_text', 'display_text', 'is_stylized', 'language_style_id', 'author_id', 'source', 'score', 'reactions_count', 'unique_reactors_count', 'history', 'awarded_at'];

    protected $hidden = ['original_text'];

    protected $attributes = ['legal_status' => 'unchecked', 'license_status' => 'none', 'commercial_status' => 'not_approved', 'source' => 'answer', 'history' => '[]'];

    protected function casts(): array
    {
        return ['is_stylized' => 'boolean', 'history' => 'array', 'awarded_at' => 'datetime'];
    }

    /** @return BelongsTo<Answer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /** @return BelongsTo<LanguageStyle, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(LanguageStyle::class, 'language_style_id');
    }

    public function label(): string
    {
        return self::PERIODS[$this->period] ?? 'Hláška';
    }

    /** Commercial use only when legal, licence AND explicit approval are all in place. */
    public function commerciallyUsable(): bool
    {
        return $this->legal_status === 'cleared' && $this->license_status === 'granted' && $this->commercial_status === 'approved';
    }
}
