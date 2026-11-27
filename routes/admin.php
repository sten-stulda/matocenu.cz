<?php

use Illuminate\Support\Facades\Route;

/*
 | Administration – every route requires a verified login, 2FA-less access is allowed
 | but privileged actions are audited. Individual pages check specific permissions.
 */
Route::middleware(['web', 'auth', 'verified', 'can:admin.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/uzivatele', 'pages::admin.users')->middleware('can:users.view')->name('users');
    Route::livewire('/role', 'pages::admin.roles')->middleware('can:roles.manage')->name('roles');
    Route::livewire('/feature-flags', 'pages::admin.features')->middleware('can:features.manage')->name('features');
    Route::livewire('/nastaveni', 'pages::admin.settings')->middleware('can:settings.manage')->name('settings');
    Route::livewire('/audit', 'pages::admin.audit')->middleware('can:audit.view')->name('audit');
    Route::livewire('/gdpr', 'pages::admin.privacy')->middleware('can:privacy.manage')->name('privacy');
    Route::livewire('/pravni-dokumenty', 'pages::admin.legal-documents')->middleware('can:legal.manage')->name('legal.documents');

    foreach (glob(__DIR__.'/admin/*.php') as $file) {
        require $file;
    }
});
