<?php

use App\Models\Section;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\SectionSeeder;
use Database\Seeders\StrandSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('demo accounts have verified logins and their intended permissions', function (string $email, string $role, string $allowedPermission, string $deniedPermission) {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', $email)->firstOrFail();

    expect($user->hasRole($role))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->can($allowedPermission))->toBeTrue()
        ->and($user->can($deniedPermission))->toBeFalse();

    $this->post(route('login.store'), [
        'email' => $email,
        'password' => 'password',
    ])->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => ['admin@edumetric.test', 'Admin', 'users.manage', 'evaluations.submit'],
    'teacher' => ['teacher@edumetric.test', 'Teacher', 'results.view-own', 'results.view-all'],
    'student' => ['student@edumetric.test', 'Student', 'evaluations.submit', 'users.manage'],
]);

test('repeated seeding preserves existing passwords and does not duplicate accounts or roles', function () {
    $user = User::factory()->create([
        'email' => 'admin@edumetric.test',
        'password' => 'a-changed-demo-password',
    ]);
    $originalPassword = $user->password;

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('sections', 4);
    $this->assertDatabaseCount('permissions', 14);
    $this->assertDatabaseCount('model_has_roles', 3);

    expect($user->fresh()->password)->toBe($originalPassword)
        ->and($user->fresh()->hasRole('Admin'))->toBeTrue();
});

test('demo account seeding can run directly', function () {
    $this->seed(DemoAccountSeeder::class);

    $this->assertDatabaseCount('users', 3);

    expect(Role::query()->where('guard_name', 'web')->count())->toBe(3);
});

test('production seeding provisions roles without demo accounts', function () {
    $this->app->instance('env', 'production');

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('users', 0);
});

test('direct demo seeding skips accounts outside development environments', function (string $environment) {
    $this->app->instance('env', $environment);

    $this->artisan('db:seed', [
        '--class' => DemoAccountSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->assertDatabaseCount('users', 0);
})->with(['production', 'staging']);

test('school reference strands can be seeded repeatedly without duplicates', function () {
    $this->seed(StrandSeeder::class);
    $this->seed(StrandSeeder::class);
    $this->assertDatabaseCount('strands', 4);
    $this->assertDatabaseHas('strands', ['code' => 'STEM', 'is_active' => true]);
});

test('starter sections seed without duplicates or replacing existing sections', function () {
    $existing = Section::factory()->create(['name' => 'A']);
    $custom = Section::factory()->create(['name' => 'School custom section']);

    $this->seed(SectionSeeder::class);
    $this->seed(SectionSeeder::class);

    $this->assertDatabaseCount('sections', 5);
    foreach (['A', 'B', 'C', 'D'] as $name) {
        $this->assertDatabaseHas('sections', ['name' => $name]);
    }
    expect(Section::where('name', 'A')->value('id'))->toBe($existing->id);
    $this->assertModelExists($custom);
});
