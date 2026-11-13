<?php

use App\Models\User;

test('two factor challenge endpoints are unavailable', function (string $method) {
    $this->call($method, '/two-factor-challenge')->assertNotFound();
})->with(['GET', 'POST']);

test('two factor management endpoints are unavailable', function (string $method, string $path) {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->call($method, $path)
        ->assertNotFound();
})->with([
    'enable' => ['POST', '/user/two-factor-authentication'],
    'disable' => ['DELETE', '/user/two-factor-authentication'],
    'confirm' => ['POST', '/user/confirmed-two-factor-authentication'],
    'QR code' => ['GET', '/user/two-factor-qr-code'],
    'secret key' => ['GET', '/user/two-factor-secret-key'],
    'recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
    'regenerate recovery codes' => ['POST', '/user/two-factor-recovery-codes'],
]);
