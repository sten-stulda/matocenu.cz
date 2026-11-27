<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/jazykove-styly', 'pages::admin.styles')->middleware('can:styles.manage')->name('styles');
Route::livewire('/ai/poskytovatele', 'pages::admin.ai-providers')->middleware('can:ai.manage')->name('ai.providers');
Route::livewire('/ai/prompty', 'pages::admin.ai-prompts')->middleware('can:prompts.manage')->name('ai.prompts');
Route::livewire('/ai/cache', 'pages::admin.ai-cache')->middleware('can:ai.manage')->name('ai.cache');
Route::livewire('/ai/naklady', 'pages::admin.ai-costs')->middleware('can:ai.costs.view')->name('ai.costs');
