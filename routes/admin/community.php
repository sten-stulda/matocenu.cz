<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/otazky', 'pages::admin.questions')->middleware('can:questions.manage')->name('questions');
Route::livewire('/otazky/{question}', 'pages::admin.question-edit')->whereNumber('question')->middleware('can:questions.manage')->name('questions.edit');
Route::livewire('/otazka-dne', 'pages::admin.daily')->middleware('can:daily.manage')->name('daily');
Route::livewire('/odpovedi', 'pages::admin.answers')->middleware('can:answers.manage')->name('answers');
Route::livewire('/komentare', 'pages::admin.comments')->middleware('can:comments.manage')->name('comments');
Route::livewire('/kategorie', 'pages::admin.categories')->middleware('can:categories.manage')->name('categories');
Route::livewire('/reakce', 'pages::admin.reactions')->middleware('can:reactions.manage')->name('reactions');
Route::livewire('/moderace', 'pages::admin.moderation')->middleware('can:moderation.manage')->name('moderation');
Route::livewire('/odvolani', 'pages::admin.appeals')->middleware('can:moderation.manage')->name('appeals');
