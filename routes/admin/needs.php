<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/potreby', 'pages::admin.needs')->middleware('can:needs.manage')->name('needs');
Route::livewire('/prilezitosti', 'pages::admin.opportunities')->middleware('can:needs.analytics')->name('opportunities');
