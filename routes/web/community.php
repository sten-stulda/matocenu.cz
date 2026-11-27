<?php

use App\Http\Controllers\Community\AnswerController;
use App\Http\Controllers\Community\CategoryController;
use App\Http\Controllers\Community\DailyQuestionController;
use App\Http\Controllers\Community\DiscoverController;
use App\Http\Controllers\Community\ProfileController;
use App\Http\Controllers\Community\QuestionController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:questions')->group(function () {
    Route::get('/otazky', [QuestionController::class, 'index'])->name('questions.index');
    Route::livewire('/otazky/nova', 'pages::questions.create')->middleware(['auth', 'verified'])->name('questions.create');
    Route::get('/otazka/{question:slug}', [QuestionController::class, 'show'])->name('questions.show');
    Route::get('/nahodna-otazka', [QuestionController::class, 'random'])->name('questions.random');
    Route::get('/odpoved/{answer}', [AnswerController::class, 'show'])->whereNumber('answer')->name('answers.show');
    Route::get('/nejvtipnejsi', [AnswerController::class, 'best'])->name('answers.best');
    Route::get('/otazka-dne', [DailyQuestionController::class, 'show'])->name('daily.show');
    Route::get('/otazka-dne/archiv', [DailyQuestionController::class, 'archive'])->name('daily.archive');
    Route::get('/objevovat', DiscoverController::class)->name('discover');
    Route::get('/kategorie', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/kategorie/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/profil/{user}', [ProfileController::class, 'show'])->whereNumber('user')->name('profiles.show');
});

Route::middleware('auth')->prefix('ucet')->name('account.')->group(function () {
    Route::get('/profil', [ProfileController::class, 'mine'])->name('profile');
    Route::livewire('/upozorneni', 'pages::account.notifications')->name('notifications');
    Route::livewire('/moderace', 'pages::account.moderation')->name('moderation');
});
