<?php

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function registrationDetails(array $overrides = []): array
{
    SchoolYear::factory()->create();

    return array_replace([
        'name' => 'Pending Teacher',
        'email' => 'pending@school.test',
        'password' => 'SchoolPassword123!',
        'password_confirmation' => 'SchoolPassword123!',
        'role' => 'Teacher',
        'strand_id' => Strand::factory()->create()->id,
        'section' => Section::factory()->create()->name,
    ], $overrides);
}

test('teachers can register with their requested cohort and no teaching assignment', function () {
    $this->seed(RolePermissionSeeder::class);
    $details = registrationDetails(['grade_level' => 12]);

    $this->post(route('register.store'), $details)->assertSessionHasNoErrors()->assertRedirectToRoute('login');

    $user = User::where('email', $details['email'])->firstOrFail();
    expect($user->hasRole('Teacher'))->toBeTrue()
        ->and($user->is_approved)->toBeFalse()
        ->and($user->grade_level)->toBeNull()
        ->and($user->section)->toBe($details['section']);
    $this->assertDatabaseCount('teacher_assignments', 0);
    $this->assertGuest();
});

test('public registration rejects privileged roles and invalid enrollment', function (array $changes, string $error) {
    $this->seed(RolePermissionSeeder::class);
    $details = registrationDetails($changes);

    $this->post(route('register.store'), $details)->assertSessionHasErrors($error);

    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
})->with([
    'administrator' => [['role' => 'Admin'], 'role'],
    'missing role' => [['role' => null], 'role'],
    'missing strand' => [['strand_id' => null], 'strand_id'],
    'unknown strand' => [['strand_id' => 99999], 'strand_id'],
    'unknown section' => [['section' => 'Unknown'], 'section'],
    'missing section' => [['section' => null], 'section'],
    'student without grade' => [['role' => 'Student'], 'grade_level'],
    'invalid grade' => [['role' => 'Student', 'grade_level' => 10], 'grade_level'],
]);

test('inactive strands cannot be chosen at registration', function () {
    $this->seed(RolePermissionSeeder::class);
    $strand = Strand::factory()->create(['is_active' => false]);
    $details = registrationDetails(['strand_id' => $strand->id]);

    $this->post(route('register.store'), $details)->assertSessionHasErrors('strand_id');

    $this->assertDatabaseCount('users', 0);
});

test('registration offers only configured enrollment choices', function () {
    $active = Strand::factory()->create();
    Strand::factory()->create(['is_active' => false]);
    $section = Section::factory()->create();

    $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')->has('strands', 1)->where('strands.0.id', $active->id)
        ->where('sections.0.name', $section->name)->missing('schoolYears'));
});

test('registration assigns the latest configured school year without a school year field', function (string $role) {
    $this->seed(RolePermissionSeeder::class);
    SchoolYear::factory()->create(['name' => '2027-2028']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $strand = Strand::factory()->create();
    $section = Section::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'New Account', 'email' => 'new@school.test',
        'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!',
        'role' => $role, 'strand_id' => $strand->id, 'section' => $section->name,
        'grade_level' => 11, 'school_year' => '1900-1901',
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('login');

    $this->assertDatabaseHas('users', ['email' => 'new@school.test', 'school_year' => '2027-2028', 'is_approved' => false]);
    $this->assertGuest();
})->with(['Teacher', 'Student']);

test('registration can await administrator review when no school year is configured', function () {
    $this->seed(RolePermissionSeeder::class);
    $strand = Strand::factory()->create();
    $section = Section::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'New Student', 'email' => 'new@school.test',
        'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!',
        'role' => 'Student', 'strand_id' => $strand->id, 'section' => $section->name, 'grade_level' => 12,
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('login');

    $this->assertDatabaseHas('users', ['email' => 'new@school.test', 'school_year' => null, 'is_approved' => false]);
    $this->assertGuest();
});

test('pending accounts cannot sign in even with valid credentials', function () {
    $user = User::factory()->create(['is_approved' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Your account is awaiting administrator approval.']);

    $this->assertGuest();
});

test('incorrect passwords do not reveal pending approval', function () {
    $user = User::factory()->create(['is_approved' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('pending sessions cannot access protected pages', function () {
    $user = User::factory()->create(['is_approved' => false]);

    $this->actingAs($user)->get(route('profile.edit'))->assertRedirectToRoute('login');

    $this->assertGuest();
});

test('administrators can approve an account without changing its access or enrollment', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $pending = User::factory()->create(['is_approved' => false, 'is_active' => false]);
    $pending->assignRole('Student');

    $this->actingAs($admin)->patch(route('users.approve', $pending), ['role' => 'Admin', 'is_active' => true])
        ->assertRedirectToRoute('users.index')->assertSessionHasNoErrors();

    expect($pending->fresh()->is_approved)->toBeTrue()
        ->and($pending->fresh()->is_active)->toBeFalse()
        ->and($pending->fresh()->hasRole('Student'))->toBeTrue()
        ->and($pending->fresh()->hasRole('Admin'))->toBeFalse();
});

test('approval permits login for active accounts', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $pending = User::factory()->create(['is_approved' => false]);
    $pending->assignRole('Teacher');
    $this->actingAs($admin)->patch(route('users.approve', $pending))->assertRedirect();
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => $pending->email, 'password' => 'password'])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($pending);
});

test('approval requires account and role management permissions', function (bool $signedIn, array $permissions) {
    $this->seed(RolePermissionSeeder::class);
    $pending = User::factory()->create(['is_approved' => false]);
    if ($signedIn) {
        $actor = User::factory()->create();
        $actor->givePermissionTo($permissions);
        $this->actingAs($actor);
    }

    $response = $this->patch(route('users.approve', $pending));

    if ($signedIn) {
        $response->assertForbidden();
    } else {
        $response->assertRedirectToRoute('login');
    }
    expect($pending->fresh()->is_approved)->toBeFalse();
})->with([
    'guest' => [false, []],
    'ordinary account' => [true, []],
    'account editor only' => [true, ['users.manage']],
    'role editor only' => [true, ['roles.assign']],
]);

test('editing a pending account does not approve it', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $pending = User::factory()->create(['is_approved' => false]);
    $pending->assignRole('Student');

    $this->actingAs($admin)->put(route('users.update', $pending), [
        'name' => 'Reviewed Name', 'email' => $pending->email, 'role' => 'Student',
        'is_active' => true, 'is_approved' => true,
    ])->assertSessionHasNoErrors();

    expect($pending->fresh()->is_approved)->toBeFalse();
    $this->get(route('users.index'))->assertInertia(fn (Assert $page) => $page
        ->where('users.data.0.id', $pending->id)->where('users.data.0.is_approved', false));
});

test('pending administrators do not permit removing the last approved administrator', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $pending = User::factory()->create(['is_approved' => false]);
    $pending->assignRole('Admin');

    $this->actingAs($admin)->put(route('users.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'Teacher', 'is_active' => false,
    ])->assertSessionHasErrors('role');

    expect($admin->fresh()->is_active)->toBeTrue()
        ->and($admin->fresh()->hasRole('Admin'))->toBeTrue();
});
