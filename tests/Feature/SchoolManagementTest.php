<?php

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;

test('administrators can create school accounts and strands', function () {
    Section::factory()->create(['name' => 'A']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $this->actingAs($admin)->post(route('strands.store'), ['name' => 'Science and Technology', 'code' => 'STEM', 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
    $strand = Strand::where('code', 'STEM')->firstOrFail();
    $this->actingAs($admin)->post(route('users.store'), ['name' => 'New Student', 'email' => 'new@student.test', 'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!', 'role' => 'Student', 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A', 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
    $student = User::where('email', 'new@student.test')->firstOrFail();
    expect($student->hasRole('Student'))->toBeTrue()->and($student->strand_id)->toBe($strand->id);
});

test('students cannot enter administrative modules', function () {
    $this->seed(DemoAccountSeeder::class);
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    foreach (['users.index', 'strands.index', 'assignments.index', 'forms.index', 'periods.index', 'reports.index'] as $route) {
        $this->actingAs($student)->get(route($route))->assertForbidden();
    }
});

test('administrators cannot deactivate the last active administrator', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Admin', 'is_active' => false])->assertSessionHasErrors('role');
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('inactive accounts cannot sign in', function () {
    $user = User::factory()->create(['is_active' => false]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('admin-created mixed-case emails are normalized and can sign in', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $data = ['name' => 'Case Teacher', 'email' => 'Teacher.Case@School.test', 'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!', 'role' => 'Teacher', 'is_active' => true];
    $this->actingAs($admin)->post(route('users.store'), $data)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => 'teacher.case@school.test']);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => 'TEACHER.CASE@SCHOOL.TEST', 'password' => 'SchoolPassword123!'])->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('admin account updates reject case variants of another account email', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $this->actingAs($admin)->put(route('users.update', $teacher), ['name' => $teacher->name, 'email' => 'ADMIN@EDUMETRIC.TEST', 'role' => 'Teacher', 'is_active' => true])->assertSessionHasErrors('email');
});

test('the sole admin cannot bypass protection through account settings deletion', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    $this->assertModelExists($admin);
});

test('school year must be consecutive', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $this->actingAs($admin)->put(route('users.update', $teacher), ['name' => $teacher->name, 'email' => $teacher->email, 'role' => 'Teacher', 'is_active' => true, 'school_year' => '2027-2026'])->assertSessionHasErrors('school_year');
});
