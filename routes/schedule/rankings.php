<?php

use App\Domain\Needs\Services\NeedsDigest;
use App\Domain\Quotes\Services\QuoteSelector;
use App\Domain\Rankings\Services\RankingEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    foreach (['day', 'week', 'month'] as $p) {
        app(RankingEngine::class)->snapshot($p);
    }
    Cache::forever('mtc:task:rankings', now()->toIso8601String());
})->hourlyAt(5)->name('rankings-snapshot')->withoutOverlapping()->onOneServer();

Schedule::call(function () {
    $sel = app(QuoteSelector::class);
    $sel->award('day');
    if (now('Europe/Prague')->isMonday()) {
        $sel->award('week');
    }
    if (now('Europe/Prague')->day === 1) {
        $sel->award('month');
    }
    Cache::forever('mtc:task:quotes', now()->toIso8601String());
})->dailyAt('00:20')->timezone('Europe/Prague')->name('quotes-award')->onOneServer();

Schedule::call(fn () => app(NeedsDigest::class)->send())
    ->weeklyOn(1, '08:00')->timezone('Europe/Prague')->name('needs-digest')->onOneServer();
