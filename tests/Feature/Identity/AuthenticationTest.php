<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Models\LegalDocument;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('logs in with correct credentials', function () {
    $user = member();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = member();
    $this->post('/login', ['email' => $user->email, 'password' => 'spatne'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('does not let suspended users log in', function () {
    $user = member();
    $user->forceFill(['status' => UserStatus::Suspended])->save();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out a user who gets suspended mid-session', function () {
    $user = member();
    $this->actingAs($user);
    $user->forceFill(['status' => UserStatus::Suspended])->save();
    $this->get('/ucet/nastaveni')->assertRedirect(route('login'));
});

it('sends and accepts the e-mail verification link', function () {
    Notification::fake();
    $user = member(null, ['email_verified_at' => null]);
    $this->actingAs($user)->post('/email/verification-notification')->assertRedirect();
    Notification::assertSentTo($user, VerifyEmail::class);

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url)->assertRedirect();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('sends a password reset link', function () {
    Notification::fake();
    $user = member();
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('logs out', function () {
    $this->actingAs(member())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('asks for consent again after a new terms version is published', function () {
    $user = member();
    LegalDocument::create(['type' => 'terms', 'version' => 2, 'title' => 'Podmínky', 'body' => 'Nové', 'requires_consent' => true, 'published_at' => now()]);

    $this->actingAs($user)->get('/ucet/nastaveni')->assertRedirect(route('consents.show'));
    $this->post('/souhlas', ['terms' => '1', 'privacy' => '1'])->assertRedirect();
    $this->get('/ucet/nastaveni')->assertOk();
});

it('hides the Google button without configuration', function () {
    config(['services.google.client_id' => null]);
    $this->get('/login')->assertDontSee('Pokračovat přes Google');
    $this->get('/auth/google/redirect')->assertNotFound();
});
