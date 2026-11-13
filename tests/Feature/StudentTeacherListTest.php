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

function teacherListScenario(?string $periodStatus = 'open'): array
{
    test()->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    Section::factory()->create(['name' => 'A']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $student->forceFill(['strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A'])->save();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A']);
    $form = EvaluationForm::factory()->create(['description' => 'Questionnaire instructions belong on the form.']);
    $criterion = $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $period = $periodStatus === null ? null : EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => $periodStatus, 'school_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

    return compact('admin', 'student', 'teacher', 'strand', 'assignment', 'form', 'criterion', 'period');
}

test('students see each assigned teacher once and open the questionnaire only through evaluate', function () {
    $this->freezeTime();
    extract(teacherListScenario());
    EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'school_year' => '2026-2027', 'status' => 'closed']);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => null, 'section' => null]);

    $this->actingAs($student)->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('teachers', 1)->where('teachers.0.id', $teacher->id)->where('teachers.0.name', $teacher->name)
        ->where('teachers.0.assignment_id', $assignment->id)->has('teachers.0.evaluations', 1)
        ->where('teachers.0.evaluations.0.period_id', $period->id)->where('teachers.0.evaluations.0.submitted', false)
        ->missing('periods')->missing('form')->missing('teachers.0.form'));
    $this->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('student/Evaluate')->where('assignment.teacher', $teacher->name)->where('form.description', 'Questionnaire instructions belong on the form.')->has('form.criteria', 1));
});

test('assigned teachers remain visible without a currently open evaluation', function (string $state) {
    $this->freezeTime();
    extract(teacherListScenario(match ($state) {
        'none' => null, 'future', 'expired', 'deadline' => 'open', default => $state
    }));
    if ($state === 'future') {
        $period->update(['opens_at' => now()->addHour()]);
    }
    if ($state === 'expired' || $state === 'deadline') {
        $period->update(['closes_at' => $state === 'deadline' ? now() : now()->subMinute()]);
    }

    $this->actingAs($student)->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('teachers', 1)->where('teachers.0.id', $teacher->id)->has('teachers.0.evaluations', 0));
})->with(['none', 'draft', 'closed', 'released', 'future', 'expired', 'deadline']);

test('teacher lists enforce enrollment and active teacher assignment restrictions', function (string $case) {
    $this->freezeTime();
    extract(teacherListScenario());
    match ($case) {
        'strand' => $student->forceFill(['strand_id' => Strand::factory()->create()->id])->save(),
        'grade' => $student->forceFill(['grade_level' => 12])->save(),
        'section' => $student->forceFill(['section' => Section::factory()->create(['name' => 'B'])->name])->save(),
        'missing school year' => $student->forceFill(['school_year' => null])->save(),
        'school year' => $student->forceFill(['school_year' => SchoolYear::factory()->create(['name' => '2027-2028'])->name])->save(),
        'assignment' => $assignment->update(['is_active' => false]),
        'teacher' => $teacher->forceFill(['is_active' => false])->save(),
        'role' => $teacher->syncRoles(['Student']),
    };

    $this->actingAs($student->fresh())->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('teachers', 0));
})->with(['strand', 'grade', 'section', 'missing school year', 'school year', 'assignment', 'teacher', 'role']);

test('submission status remains separate for each open period of a teacher', function () {
    $this->freezeTime();
    extract(teacherListScenario());
    $otherPeriod = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'school_year' => '2026-2027', 'status' => 'open', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$criterion->id => 4]])->assertSessionHasNoErrors();

    $this->get(route('evaluations.index'))->assertInertia(fn (Assert $page) => $page
        ->has('teachers', 1)->has('teachers.0.evaluations', 2)
        ->where('teachers.0.evaluations.0.period_id', $otherPeriod->id)->where('teachers.0.evaluations.0.submitted', false)
        ->where('teachers.0.evaluations.1.period_id', $period->id)->where('teachers.0.evaluations.1.submitted', true));
    $this->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment]))->assertRedirectToRoute('evaluations.index');
    $this->get(route('evaluations.create', ['period' => $otherPeriod, 'assignment' => $assignment]))->assertOk();
});
