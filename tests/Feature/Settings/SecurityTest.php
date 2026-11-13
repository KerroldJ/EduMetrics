<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('removed settings pages are unavailable', function (string $path) {
    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertNotFound();
})->with([
    'security' => ['/settings/security'],
    'appearance' => ['/settings/appearance'],
    'passkey discovery' => ['/.well-known/passkey-endpoints'],
]);

test('password changes through settings are unavailable', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertNotFound();

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('account deletion through profile settings is unavailable', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);

    $this->assertAuthenticatedAs($user);
    $this->assertModelExists($user);
});

test('passkey endpoints are unavailable', function (string $method, string $path) {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->call($method, $path)
        ->assertNotFound();
})->with([
    'login options' => ['GET', '/passkeys/login/options'],
    'login' => ['POST', '/passkeys/login'],
    'confirmation options' => ['GET', '/passkeys/confirm/options'],
    'confirmation' => ['POST', '/passkeys/confirm'],
    'registration options' => ['GET', '/user/passkeys/options'],
    'registration' => ['POST', '/user/passkeys'],
    'deletion' => ['DELETE', '/user/passkeys/1'],
]);
