<?php

use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('audience', 'member')
        ->where('overview', null));
});

test('administrators see real account counts and a limited account list', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::query()->where('email', 'admin@edumetric.test')->firstOrFail();
    User::factory()->count(2)->create()->each(fn (User $user) => $user->assignRole('Teacher'));
    User::factory()->unverified()->create()->assignRole('Student');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('audience', 'admin')
        ->where('roleLabel', 'Admin')
        ->where('overview.teacherCount', 3)
        ->where('overview.studentCount', 2)
        ->where('overview.accountCount', 6)
        ->where('overview.verifiedCount', 5)
        ->has('overview.recentAccounts', 5)
        ->missing('overview.recentAccounts.0.password')
        ->missing('overview.recentAccounts.0.two_factor_secret'));
});

test('teachers and students see their own dashboard without administrative account data', function (string $email, string $audience, string $role) {
    $this->seed(DemoAccountSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('audience', $audience)
        ->where('roleLabel', $role)
        ->where('overview', null));
})->with([
    'teacher' => ['teacher@edumetric.test', 'teacher', 'Teacher'],
    'student' => ['student@edumetric.test', 'student', 'Student'],
]);

test('unverified users must verify their email before viewing the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirectToRoute('verification.notice');
});
