<?php

use App\Http\Controllers\Needs\NeedController;
use App\Http\Controllers\Needs\OpportunityController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:needs')->group(function () {
    Route::get('/co-lidem-chybi', [NeedController::class, 'index'])->name('needs.index');
    Route::livewire('/co-lidem-chybi/nova', 'pages::needs.create')->middleware(['auth', 'verified'])->name('needs.create');
    Route::get('/potreba/{need:slug}', [NeedController::class, 'show'])->name('needs.show');
});
Route::get('/prilezitosti', OpportunityController::class)->middleware('feature:opportunities')->name('opportunities.index');
