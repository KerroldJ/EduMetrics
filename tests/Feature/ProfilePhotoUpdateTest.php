<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('users update their own contact details and photo without changing protected account data', function () {
    Storage::fake('public');
    $user = User::factory()->create(['mobile' => null]);
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'patch', 'name' => 'Updated profile', 'email' => $user->email, 'mobile' => '+639171234567',
        'photo' => UploadedFile::fake()->image('profile.png'), 'user_id' => $other->id, 'is_active' => false, 'role' => 'Admin', 'avatar_path' => 'injected.jpg',
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('profile.edit');

    $user->refresh();
    expect($user->name)->toBe('Updated profile');
    expect($user->mobile)->toBe('+639171234567');
    expect($user->is_active)->toBeTrue();
    expect($user->getRoleNames())->toBeEmpty();
    expect($user->avatar_path)->toStartWith('profile-photos/');
    expect($other->fresh()->name)->toBe($other->name);
    Storage::disk('public')->assertExists($user->avatar_path);
    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', $user->avatar));
});

test('profile photo replacement removes the previous image after saving', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('old.png')->store('profile-photos', 'public');
    $user = User::factory()->create(['avatar_path' => $path]);

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'patch', 'name' => $user->name, 'email' => $user->email,
        'photo' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->avatar_path)->not->toBe($path);
    Storage::disk('public')->assertMissing($path);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('profile edits preserve the photo until removal is explicitly requested', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('old.png')->store('profile-photos', 'public');
    $user = User::factory()->create(['avatar_path' => $path]);
    $payload = ['name' => $user->name, 'email' => $user->email, 'photo' => null];

    $this->actingAs($user)->patch(route('profile.update'), $payload)->assertSessionHasNoErrors();

    expect($user->fresh()->avatar_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
    $this->patch(route('profile.update'), [...$payload, 'remove_photo' => true])->assertSessionHasNoErrors();
    expect($user->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('invalid profile photos and contact data do not change the existing profile', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('old.png')->store('profile-photos', 'public');
    $user = User::factory()->create(['avatar_path' => $path, 'mobile' => '+639171234567']);

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'patch', 'name' => 'Should not save', 'email' => $user->email, 'mobile' => 'invalid',
        'photo' => UploadedFile::fake()->image('large.jpg')->size(2049),
    ])->assertSessionHasErrors(['mobile', 'photo']);

    expect($user->fresh()->name)->toBe($user->name);
    expect($user->fresh()->avatar_path)->toBe($path);
    expect(Storage::disk('public')->allFiles())->toBe([$path]);
});
