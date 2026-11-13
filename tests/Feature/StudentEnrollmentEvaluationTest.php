<?php

use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('user management receives the managed school years needed to assign student enrollment', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    SchoolYear::factory()->create(['name' => '2026-2027']);
    SchoolYear::factory()->create(['name' => '2027-2028']);

    $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Users')->has('schoolYears', 2)->where('schoolYears.0.name', '2027-2028')->where('schoolYears.1.name', '2026-2027'));
});

test('assigning a missing student school year makes matching open evaluations available', function () {
    $this->freezeTime();
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create(['code' => 'ABM']);
    Section::factory()->create(['name' => 'A']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $student->forceFill(['strand_id' => $strand->id, 'school_year' => null, 'grade_level' => 11, 'section' => 'A'])->save();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A']);
    $form = EvaluationForm::factory()->create();
    $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'school_year' => '2026-2027', 'status' => 'open', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
    $this->actingAs($student)->get(route('evaluations.index'))->assertInertia(fn (Assert $page) => $page->where('enrollment.school_year', null)->has('teachers', 0));
    $this->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment]))->assertForbidden();

    $this->actingAs($admin)->put(route('users.update', $student), ['name' => $student->name, 'email' => $student->email, 'role' => 'Student', 'is_active' => true, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A'])->assertSessionHasNoErrors();

    expect($student->fresh()->school_year)->toBe('2026-2027');
    $this->actingAs($student->fresh())->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('enrollment.school_year', '2026-2027')->has('teachers', 1)->where('teachers.0.evaluations.0.period_id', $period->id)->where('teachers.0.assignment_id', $assignment->id));
    $this->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('form.criteria', 1));
    $this->assertDatabaseCount('evaluations', 0);
});
