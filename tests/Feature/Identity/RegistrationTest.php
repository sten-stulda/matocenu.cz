<?php

use App\Models\ConsentRecord;
use App\Models\User;

$valid = fn (array $o = []) => array_merge([
    'name' => 'Pepa z Kroměříže',
    'email' => 'pepa@example.com',
    'password' => 'Bezpecne-Heslo-2026x',
    'password_confirmation' => 'Bezpecne-Heslo-2026x',
    'terms' => '1',
    'privacy' => '1',
], $o);

it('shows the registration page', function () {
    $this->get('/register')->assertOk()->assertSee('Registrace')->assertSee('Přezdívka');
});

it('registers a user with role, settings and consents', function () use ($valid) {
    $this->post('/register', $valid(['marketing' => '1']))->assertRedirect();

    $user = User::where('email', 'pepa@example.com')->firstOrFail();
    expect($user->name)->toBe('Pepa z Kroměříže')
        ->and($user->hasRole('user'))->toBeTrue()
        ->and($user->settings)->not->toBeNull()
        ->and($user->settings->marketing_emails)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    expect(ConsentRecord::where('user_id', $user->id)->pluck('consent_type')->all())
        ->toContain('terms', 'privacy', 'marketing');
    $this->assertAuthenticatedAs($user);
});

it('requires consent with terms', function () use ($valid) {
    $this->post('/register', $valid(['terms' => null]))->assertSessionHasErrors('terms');
    expect(User::count())->toBe(0);
});

it('rejects case-insensitive duplicate nicknames and reserved names', function () use ($valid) {
    member(null, ['name' => 'Bára_Ostrava']);
    $this->post('/register', $valid(['name' => 'bára_ostrava']))->assertSessionHasErrors('name');
    $this->post('/register', $valid(['name' => 'Admin', 'email' => 'x@example.com']))->assertSessionHasErrors('name');
});

it('blocks bots filling the honeypot', function () use ($valid) {
    $this->post('/register', $valid(['website' => 'http://spam']))->assertSessionHasErrors('email');
    expect(User::count())->toBe(0);
});

it('lowercases e-mail and keeps Czech diacritics in nickname', function () use ($valid) {
    $this->post('/register', $valid(['email' => 'Pepa@Example.COM', 'name' => 'Žluťoučký kůň']));
    expect(User::first())->email->toBe('pepa@example.com')->name->toBe('Žluťoučký kůň');
});
