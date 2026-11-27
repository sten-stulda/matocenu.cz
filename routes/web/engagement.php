<?php

use App\Http\Controllers\Community\CardController;
use App\Http\Controllers\Community\QuoteController;
use App\Http\Controllers\Community\RankingController;
use App\Http\Controllers\Community\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:questions')->group(function () {
    Route::get('/zebricky', [RankingController::class, 'index'])->name('rankings.index');
    Route::view('/zebricky/jak-pocitame', 'pages.rankings.method')->name('rankings.method');
    Route::get('/hledat', SearchController::class)->middleware('throttle:60,1')->name('search');
    Route::get('/karta/odpoved/{answer}.png', [CardController::class, 'answer'])->whereNumber('answer')->middleware('throttle:120,1')->name('cards.answer');
    Route::get('/karta/otazka/{question:slug}.png', [CardController::class, 'question'])->middleware('throttle:120,1')->name('cards.question');
});
Route::get('/hlasky', [QuoteController::class, 'index'])->middleware('feature:quotes')->name('quotes.index');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
