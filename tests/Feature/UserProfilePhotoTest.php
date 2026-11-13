<?php

use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators can create accounts with profile photos visible in user and teacher profiles', function (string $extension) {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'A Photo Teacher', 'email' => 'photo@edumetric.test', 'role' => 'Teacher', 'is_active' => true,
        'password' => 'password', 'password_confirmation' => 'password',
        'photo' => UploadedFile::fake()->image('profile.'.$extension),
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('users.index');

    $teacher = User::where('email', 'photo@edumetric.test')->firstOrFail();
    expect($teacher->avatar_path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($teacher->avatar_path);
    expect($teacher->avatar)->toBe(Storage::disk('public')->url($teacher->avatar_path));
    expect($teacher->toArray())->not->toHaveKey('avatar_path');
    $this->get(route('users.index', ['q' => $teacher->email]))->assertInertia(fn (Assert $page) => $page
        ->where('users.data.0.avatar', $teacher->avatar)->missing('users.data.0.avatar_path'));
    $this->get(route('results.index'))->assertInertia(fn (Assert $page) => $page
        ->where('teachers.0.avatar', $teacher->avatar)->missing('teachers.0.avatar_path'));
    $this->actingAs($teacher)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', $teacher->avatar));
})->with(['jpg', 'png', 'webp']);

test('multipart account updates replace photos and delete the old file while keeping blank passwords unchanged', function () {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('profile-photos', 'public');
    $teacher->forceFill(['avatar_path' => $oldPath])->save();
    $originalPassword = $teacher->password;

    $this->actingAs($admin)->post(route('users.update', $teacher), [
        ...$teacher->only(['name', 'email']), '_method' => 'put', 'role' => 'Teacher', 'is_active' => true,
        'password' => '', 'photo' => UploadedFile::fake()->image('new.png'),
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('users.index');

    $teacher->refresh();
    expect($teacher->avatar_path)->not->toBe($oldPath);
    expect($teacher->password)->toBe($originalPassword);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($teacher->avatar_path);
});

test('editing without a photo preserves the existing picture and ignores supplied storage paths', function () {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('profile-photos', 'public');
    $teacher->forceFill(['avatar_path' => $oldPath])->save();

    $this->actingAs($admin)->put(route('users.update', $teacher), [
        ...$teacher->only(['name', 'email']), 'role' => 'Teacher', 'is_active' => true,
        'photo' => null, 'avatar_path' => 'another-users-photo.jpg',
    ])->assertSessionHasNoErrors();

    expect($teacher->fresh()->avatar_path)->toBe($oldPath);
    Storage::disk('public')->assertExists($oldPath);
});

test('administrators can remove a profile picture', function () {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('profile-photos', 'public');
    $teacher->forceFill(['avatar_path' => $oldPath])->save();

    $this->actingAs($admin)->put(route('users.update', $teacher), [
        ...$teacher->only(['name', 'email']), 'role' => 'Teacher', 'is_active' => true, 'remove_photo' => true,
    ])->assertSessionHasNoErrors();

    expect($teacher->fresh()->avatar)->toBeNull();
    expect($teacher->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);
});

test('invalid photos do not update the account or replace its existing file', function (Closure $photo) {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('profile-photos', 'public');
    $teacher->forceFill(['avatar_path' => $oldPath])->save();

    $this->actingAs($admin)->post(route('users.update', $teacher), [
        ...$teacher->only(['name', 'email']), '_method' => 'put', 'role' => 'Teacher', 'is_active' => true, 'photo' => $photo(),
    ])->assertSessionHasErrors('photo');

    expect($teacher->fresh()->avatar_path)->toBe($oldPath);
    expect(Storage::disk('public')->allFiles())->toBe([$oldPath]);
})->with([
    'oversized image' => fn () => UploadedFile::fake()->image('large.jpg')->size(2049),
    'disguised text' => fn () => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
    'active SVG' => fn () => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'excessive dimensions' => fn () => UploadedFile::fake()->image('wide.jpg', 4001, 2),
]);

test('a rejected last administrator change does not store an uploaded photo', function () {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('users.update', $admin), [
        ...$admin->only(['name', 'email']), '_method' => 'put', 'role' => 'Teacher', 'is_active' => true,
        'photo' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSessionHasErrors('role');

    expect($admin->fresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('teachers cannot upload profile photos through user management', function () {
    Storage::fake('public');
    $this->seed(DemoAccountSeeder::class);
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();

    $this->actingAs($teacher)->post(route('users.update', $teacher), [
        ...$teacher->only(['name', 'email']), '_method' => 'put', 'role' => 'Teacher', 'is_active' => true,
        'photo' => UploadedFile::fake()->image('new.jpg'),
    ])->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
