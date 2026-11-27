<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/zebricky', 'pages::admin.rankings')->middleware('can:rankings.manage')->name('rankings');
Route::livewire('/hlasky', 'pages::admin.quotes')->middleware('can:quotes.manage')->name('quotes');
