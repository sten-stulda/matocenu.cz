<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PrivacyExportController;
use App\Http\Controllers\RobotsController;
use Illuminate\Support\Facades\Route;

/*
 | Public web. Module routes are split into routes/web/*.php and loaded below.
 */
Route::get('/', HomeController::class)->name('home');
Route::get('/pravni/{slug}', [LegalController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('legal.show');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/health', [HealthController::class, 'live'])->name('health');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/souhlas', [ConsentController::class, 'show'])->name('consents.show');
    Route::post('/souhlas', [ConsentController::class, 'store'])->name('consents.store');
});

Route::middleware(['auth'])->prefix('ucet')->name('account.')->group(function () {
    Route::livewire('/nastaveni', 'pages::account.settings')->name('settings');
    Route::livewire('/zabezpeceni', 'pages::account.security')->middleware('password.confirm')->name('security');
    Route::livewire('/soukromi', 'pages::account.privacy')->name('privacy');
    Route::get('/export', PrivacyExportController::class)->middleware(['password.confirm', 'throttle:export'])->name('export');
});

foreach (glob(__DIR__.'/web/*.php') as $file) {
    require $file;
}
