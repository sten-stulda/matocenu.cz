<?php

use App\Models\LanguageStyle;
use App\Models\ReactionType;

// Regression: Laravel 13 refuses to unserialize objects from the cache (cache.serializable_classes = false),
// so a cached Eloquent collection became __PHP_Incomplete_Class and the homepage returned 500 on the 2nd hit.
it('serves the homepage repeatedly from a warm cache', function () {
    $q = liveQuestion();
    answerOf($q, member(), 'Klid.');
    $this->get('/')->assertOk();
    $this->get('/')->assertOk();
    $this->actingAs(member())->get('/')->assertOk();
});

it('reads cached reaction types and styles back as models', function () {
    ReactionType::active();
    LanguageStyle::active();
    expect(ReactionType::active()->first())->toBeInstanceOf(ReactionType::class)
        ->and(LanguageStyle::active()->first())->toBeInstanceOf(LanguageStyle::class)
        ->and(ReactionType::active()->first()->exists)->toBeTrue();
});
