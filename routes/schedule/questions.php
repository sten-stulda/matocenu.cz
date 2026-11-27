<?php

use App\Domain\Questions\Services\DailyQuestions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    app(DailyQuestions::class)->publishDue();
    Cache::forever('mtc:task:daily_publish', now()->toIso8601String());
})->everyMinute()->name('daily-question-publish')->withoutOverlapping()->onOneServer();

Schedule::call(function () {
    app(DailyQuestions::class)->evaluatePending();
    Cache::forever('mtc:task:daily_evaluate', now()->toIso8601String());
})->dailyAt('00:10')->timezone('Europe/Prague')->name('daily-question-evaluate')->onOneServer();
