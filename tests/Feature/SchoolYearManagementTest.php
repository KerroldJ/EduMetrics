<?php

use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators can create list edit and delete unused school years', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('school-years.store'), ['name' => ' 2026-2027 '])->assertSessionHasNoErrors()->assertRedirectToRoute('strands.index', ['tab' => 'school-years']);
    $year = SchoolYear::where('name', '2026-2027')->firstOrFail();
    $this->get(route('strands.index', ['tab' => 'school-years']))->assertInertia(fn (Assert $page) => $page->where('activeTab', 'school-years')->where('schoolYears.0.name', '2026-2027'));
    $this->put(route('school-years.update', $year), ['name' => '2027-2028'])->assertSessionHasNoErrors();
    expect($year->fresh()->name)->toBe('2027-2028');
    $this->delete(route('school-years.destroy', $year))->assertSessionHasNoErrors()->assertRedirect();
    $this->assertModelMissing($year);
});

test('school year renames keep enrollment assignments and evaluation periods aligned', function () {
    $this->seed(DemoAccountSeeder::class);
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $student->forceFill(['school_year' => $year->name, 'strand_id' => $strand->id])->save();
    $assignment = TeacherAssignment::factory()->create(['school_year' => $year->name, 'teacher_id' => $teacher->id, 'strand_id' => $strand->id]);
    $period = EvaluationPeriod::factory()->create(['school_year' => $year->name, 'status' => 'open']);

    $this->actingAs($admin)->put(route('school-years.update', $year), ['name' => '2027-2028'])->assertSessionHasNoErrors();

    expect($student->fresh()->school_year)->toBe('2027-2028');
    expect($assignment->fresh()->school_year)->toBe('2027-2028');
    expect($period->fresh()->school_year)->toBe('2027-2028');
    $this->actingAs($student->fresh())->get(route('evaluations.index'))->assertInertia(fn (Assert $page) => $page->where('periods.0.assignments.0.id', $assignment->id));
});

test('school years referenced by enrollment assignments or periods cannot be deleted', function (string $usage) {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);
    match ($usage) {
        'account' => User::factory()->create()->forceFill(['school_year' => $year->name])->save(),
        'assignment' => TeacherAssignment::factory()->create(['school_year' => $year->name]),
        'period' => EvaluationPeriod::factory()->create(['school_year' => $year->name]),
    };

    $this->actingAs($admin)->delete(route('school-years.destroy', $year))->assertSessionHasErrors('school_year');

    $this->assertModelExists($year);
})->with(['account', 'assignment', 'period']);

test('teacher assignments only accept school years from the managed dropdown', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);
    $strand = Strand::factory()->create();
    $data = ['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2030-2031', 'is_active' => true];

    $this->actingAs($admin)->get(route('assignments.index'))->assertInertia(fn (Assert $page) => $page->where('schoolYears.0.name', '2026-2027'));
    $this->post(route('assignments.store'), $data)->assertSessionHasErrors('school_year');
    $this->assertDatabaseCount('teacher_assignments', 0);
    $this->post(route('assignments.store'), [...$data, 'school_year' => $year->name])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('teacher_assignments', ['school_year' => '2026-2027']);
});

test('evaluation periods use managed school years', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    SchoolYear::factory()->create(['name' => '2026-2027']);

    $this->actingAs($admin)->get(route('periods.index'))->assertInertia(fn (Assert $page) => $page->where('schoolYears.0.name', '2026-2027'));
    $this->post(route('periods.store'), ['school_year' => '2030-2031'])->assertSessionHasErrors('school_year');
    $this->assertDatabaseCount('evaluation_periods', 0);
});

test('school year names are consecutive and unique', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);
    $other = SchoolYear::factory()->create(['name' => '2027-2028']);

    $this->actingAs($admin)->post(route('school-years.store'), ['name' => '2026-2028'])->assertSessionHasErrors('name');
    $this->post(route('school-years.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->post(route('school-years.store'), ['name' => '2026-2027'])->assertSessionHasErrors('name');
    $this->put(route('school-years.update', $other), ['name' => '2026-2027'])->assertSessionHasErrors('name');
    $this->put(route('school-years.update', $year), ['name' => '2026-2027'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('school_years', 2);
});

test('students and teachers cannot manage school years', function (string $email) {
    $this->seed(DemoAccountSeeder::class);
    $user = User::where('email', $email)->firstOrFail();
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);

    $this->actingAs($user)->post(route('school-years.store'), ['name' => '2027-2028'])->assertForbidden();
    $this->put(route('school-years.update', $year), ['name' => '2027-2028'])->assertForbidden();
    $this->delete(route('school-years.destroy', $year))->assertForbidden();
    expect($year->fresh()->name)->toBe('2026-2027');
    $this->assertDatabaseCount('school_years', 1);
})->with(['student@edumetric.test', 'teacher@edumetric.test']);

test('the school year migration imports existing values without changing them', function () {
    $migration = require database_path('migrations/2026_11_11_213154_create_school_years_table.php');
    $migration->down();
    $user = User::factory()->create();
    $user->forceFill(['school_year' => '2020-2021'])->save();
    $assignment = TeacherAssignment::factory()->create(['school_year' => '2021-2022']);
    $period = EvaluationPeriod::factory()->create(['school_year' => '2022-2023']);

    $migration->up();

    $this->assertDatabaseCount('school_years', 3);
    expect($user->fresh()->school_year)->toBe('2020-2021');
    expect($assignment->fresh()->school_year)->toBe('2021-2022');
    expect($period->fresh()->school_year)->toBe('2022-2023');
});

test('account enrollment rejects school years outside the managed list', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $year = SchoolYear::factory()->create(['name' => '2026-2027']);
    $data = ['name' => 'Year Student', 'email' => 'year@student.test', 'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!', 'role' => 'Student', 'school_year' => '2030-2031', 'is_active' => true];

    $this->actingAs($admin)->post(route('users.store'), $data)->assertSessionHasErrors('school_year');
    $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    $this->post(route('users.store'), [...$data, 'school_year' => $year->name])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => $data['email'], 'school_year' => '2026-2027']);
});
