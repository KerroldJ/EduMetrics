<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('users can change their own password from profile and sign in with the new password', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherPassword = $other->password;
    $rememberToken = $user->remember_token;

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'NewProfilePassword123!', 'password_confirmation' => 'NewProfilePassword123!', 'user_id' => $other->id,
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('profile.edit');

    expect(Hash::check('NewProfilePassword123!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe($rememberToken);
    expect($other->fresh()->password)->toBe($otherPassword);
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'NewProfilePassword123!'])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('invalid profile password changes preserve the password and remember token', function (array $fields, string $error) {
    $user = User::factory()->create();
    $password = $user->password;
    $token = $user->remember_token;

    $this->actingAs($user)->put(route('profile.password.update'), $fields)->assertSessionHasErrors($error);

    expect($user->fresh()->password)->toBe($password);
    expect($user->fresh()->remember_token)->toBe($token);
})->with([
    'incorrect current password' => [['current_password' => 'incorrect', 'password' => 'NewProfilePassword123!', 'password_confirmation' => 'NewProfilePassword123!'], 'current_password'],
    'missing current password' => [['password' => 'NewProfilePassword123!', 'password_confirmation' => 'NewProfilePassword123!'], 'current_password'],
    'confirmation mismatch' => [['current_password' => 'password', 'password' => 'NewProfilePassword123!', 'password_confirmation' => 'DifferentPassword123!'], 'password'],
    'weak new password' => [['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'missing new password' => [['current_password' => 'password'], 'password'],
]);

test('profile password changes require authentication', function () {
    $this->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'NewProfilePassword123!', 'password_confirmation' => 'NewProfilePassword123!',
    ])->assertRedirectToRoute('login');
});
