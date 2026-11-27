<?php

use App\Models\User;

it('renders the homepage without registration and without invented content', function () {
    $this->get('/')->assertOk()->assertSee('Otázka dne se právě vaří.')->assertDontSee('UKÁZKOVÁ DATA');
});

it('sends security headers with a CSP nonce', function () {
    $res = $this->get('/');
    $csp = $res->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toMatch("/'nonce-[A-Za-z0-9]+'/");
    expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('shows legal documents as drafts for review', function () {
    $this->get('/pravni/podminky')->assertOk()->assertSee('Pracovní návrh');
    $this->get('/pravni/neexistuje')->assertNotFound();
});

it('blocks indexing outside production', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    $this->get('/')->assertSee('noindex', false);
});

it('reports health', function () {
    $this->get('/health')->assertOk()->assertJson(['status' => 'ok']);
    $this->get('/health/ready')->assertJsonPath('checks.database', 'ok');
});

it('renders Czech error pages', function () {
    $this->get('/neexistuje')->assertNotFound()->assertSee('Tož tady nic není');
});

it('provides a working create-admin command', function () {
    $this->artisan('matocenu:create-admin', ['email' => 'Spravce@Example.com', 'name' => 'Správce'])->assertSuccessful();
    $u = User::where('email', 'spravce@example.com')->first();
    expect($u->hasRole('superadmin'))->toBeTrue()->and($u->hasVerifiedEmail())->toBeTrue();
});
