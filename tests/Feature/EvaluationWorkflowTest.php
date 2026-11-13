<?php

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function evaluationScenario(): array
{
    test()->seed(DemoAccountSeeder::class);
    Section::factory()->create(['name' => 'A']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $student->forceFill(['strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A'])->save();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A']);
    $form = EvaluationForm::factory()->create();
    $first = $form->criteria()->create(['prompt' => 'Clear explanations', 'weight' => 2, 'position' => 1]);
    $second = $form->criteria()->create(['prompt' => 'Useful feedback', 'weight' => 1, 'position' => 2]);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'school_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'status' => 'open', 'minimum_responses' => 1]);

    return compact('admin', 'teacher', 'student', 'strand', 'assignment', 'form', 'first', 'second', 'period');
}

test('students evaluate eligible teachers and teachers see only their released anonymous results', function () {
    extract(evaluationScenario());
    $payload = ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 1], 'comment' => 'Helpful lessons'];
    $this->actingAs($student)->post(route('evaluations.store', $period), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $evaluation = Evaluation::firstOrFail();
    expect((float) $evaluation->score)->toBe(3.0);
    $this->assertDatabaseCount('evaluation_responses', 2);
    $this->actingAs($student)->post(route('evaluations.store', $period), $payload)->assertSessionHasErrors('evaluation');
    $this->assertDatabaseCount('evaluations', 1);
    $this->actingAs($teacher)->get(route('results.index'))->assertInertia(fn (Assert $page) => $page->has('results', 0));
    $this->actingAs($admin)->post(route('periods.transition', $period), ['action' => 'close'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('periods.transition', $period), ['action' => 'release'])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->get(route('results.index'))->assertInertia(fn (Assert $page) => $page->has('results', 1)->where('results.0.score', 3)->where('results.0.comments.0', 'Helpful lessons')->missing('results.0.student_id')->missing('results.0.student'));
    $other = User::factory()->create();
    $other->assignRole('Teacher');
    $this->actingAs($other)->get(route('results.index'))->assertInertia(fn (Assert $page) => $page->has('results', 0));
    $this->actingAs($admin)->get(route('reports.download', ['period' => $period->id]))->assertOk()->assertDownload();
});

test('wrong strand invalid answers and closed periods never save evaluations', function () {
    extract(evaluationScenario());
    $other = Strand::factory()->create();
    $student->forceFill(['strand_id' => $other->id])->save();
    $payload = ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 2]];
    $this->actingAs($student)->post(route('evaluations.store', $period), $payload)->assertForbidden();
    $student->forceFill(['strand_id' => $strand->id])->save();
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 99]])->assertSessionHasErrors('ratings.'.$first->id);
    $period->update(['closes_at' => now()->subMinute()]);
    $this->actingAs($student)->post(route('evaluations.store', $period), $payload)->assertSessionHasErrors('evaluation');
    $this->assertDatabaseCount('evaluations', 0);
    $this->assertDatabaseCount('evaluation_responses', 0);
});

test('forms cannot change after a period uses them', function () {
    extract(evaluationScenario());
    $this->actingAs($admin)->put(route('forms.update', $form), ['name' => 'Changed', 'scale_min' => 1, 'scale_max' => 5, 'is_active' => true, 'criteria' => [['prompt' => 'New prompt', 'weight' => 1]]])->assertSessionHasErrors('form');
    expect($form->fresh()->criteria()->count())->toBe(2);
});

test('minimum response thresholds protect teacher feedback', function () {
    extract(evaluationScenario());
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 4]])->assertSessionHasNoErrors();
    $period->update(['status' => 'released', 'minimum_responses' => 3]);
    $this->actingAs($teacher)->get(route('results.index'))->assertInertia(fn (Assert $page) => $page->has('results', 0));
});

test('administrators can configure forms assignments and periods through real endpoints', function () {
    test()->seed(DemoAccountSeeder::class);
    Section::factory()->create(['name' => 'A']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $this->actingAs($admin)->post(route('assignments.store'), ['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A', 'is_active' => true])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('forms.store'), ['name' => 'School instrument', 'scale_min' => 1, 'scale_max' => 5, 'is_active' => true, 'criteria' => [['prompt' => 'Clear instruction', 'weight' => 2], ['prompt' => 'Helpful feedback', 'weight' => 1]]])->assertSessionHasNoErrors();
    $form = EvaluationForm::firstOrFail();
    $this->actingAs($admin)->post(route('periods.store'), ['name' => 'First semester', 'school_year' => '2026-2027', 'evaluation_form_id' => $form->id, 'opens_at' => now()->subHour()->toIso8601String(), 'closes_at' => now()->addDay()->toIso8601String(), 'minimum_responses' => 3])->assertSessionHasNoErrors();
    $period = EvaluationPeriod::firstOrFail();
    expect($period->status)->toBe('draft');
    $this->actingAs($admin)->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasNoErrors();
    expect($period->fresh()->isOpen())->toBeTrue();
    $this->actingAs($admin)->get(route('strands.index', ['tab' => 'assignments']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('activeTab', 'assignments')->has('assignments.data', 1));
    foreach (['users.index', 'strands.index', 'forms.index', 'periods.index', 'reports.index'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

test('eligibility rejects wrong grade section year and inactive assignments', function (string $field, mixed $value) {
    extract(evaluationScenario());
    if ($field === 'is_active') {
        $assignment->update(['is_active' => false]);
    } else {
        if ($field === 'school_year') {
            SchoolYear::factory()->create(['name' => $value]);
        }
        if ($field === 'section') {
            Section::factory()->create(['name' => $value]);
        }
        $student->forceFill([$field => $value])->save();
    }
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 3, $second->id => 3]])->assertForbidden();
    $this->assertDatabaseCount('evaluations', 0);
})->with(['grade' => ['grade_level', 12], 'section' => ['section', 'B'], 'year' => ['school_year', '2027-2028'], 'inactive assignment' => ['is_active', false]]);

test('incomplete and foreign question keys never save a partial evaluation', function (string $case) {
    extract(evaluationScenario());
    $ratings = match ($case) {
        'missing' => [$first->id => 3],'foreign' => [$first->id => 3, 999999 => 3],default => ['0'.$first->id => 3, $second->id => 3]
    };
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => $ratings])->assertSessionHasErrors('ratings');
    $this->assertDatabaseCount('evaluations', 0);
    $this->assertDatabaseCount('evaluation_responses', 0);
})->with(['missing', 'foreign', 'noncanonical key']);

test('referenced school accounts cannot be deleted through settings', function () {
    extract(evaluationScenario());
    $this->actingAs($teacher)->delete('/settings/profile', ['password' => 'password'])->assertMethodNotAllowed();
    $this->assertModelExists($teacher);
});

test('an assignment can be deactivated after its strand has been retired', function () {
    extract(evaluationScenario());
    $strand->update(['is_active' => false]);
    $this->actingAs($admin)->patch(route('assignments.status', $assignment), ['is_active' => false])->assertSessionHasNoErrors()->assertRedirect();
    expect($assignment->fresh()->is_active)->toBeFalse();
});

test('schedule opening and closing boundaries reject submissions', function (string $boundary) {
    $this->freezeTime();
    extract(evaluationScenario());
    $period->update($boundary === 'future' ? ['opens_at' => now()->addHour()] : ['closes_at' => now()]);
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 3, $second->id => 3]])->assertSessionHasErrors('evaluation');
    $this->assertDatabaseCount('evaluations', 0);
})->with(['future', 'deadline']);

test('administrators can open both evaluation portals and select a student', function () {
    extract(evaluationScenario());

    $this->actingAs($admin)->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('student/Evaluations')->where('adminView', true)->has('students', 1)->where('students.0.id', $student->id)->where('selectedStudent', null)->has('teachers', 0));
    $this->get(route('evaluations.index', ['student_id' => $student->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedStudent.id', $student->id)->where('strand.id', $strand->id)->has('teachers', 1)->where('teachers.0.assignment_id', $assignment->id));
    $this->get(route('results.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('faculty/Results')->where('adminView', true)->has('teachers', 1)->where('selectedTeacher', null)->has('results', 0));
});

test('administrators can preview an eligible questionnaire without submitting for a student', function () {
    extract(evaluationScenario());

    $this->actingAs($admin)->get(route('evaluations.create', ['period' => $period->id, 'assignment' => $assignment->id, 'student_id' => $student->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('student/Evaluate')->where('adminView', true)->where('selectedStudent.id', $student->id)->has('form.criteria', 2));
    $this->post(route('evaluations.store', $period), ['student_id' => $student->id, 'assignment_id' => $assignment->id, 'ratings' => [$first->id => 5, $second->id => 5]])->assertForbidden();
    $this->assertDatabaseCount('evaluations', 0);
});

test('administrator questionnaire previews enforce the selected students enrollment', function () {
    extract(evaluationScenario());
    Section::factory()->create(['name' => 'Another section']);
    $student->forceFill(['section' => 'Another section'])->save();

    $this->actingAs($admin)->get(route('evaluations.create', ['period' => $period->id, 'assignment' => $assignment->id, 'student_id' => $student->id]))->assertForbidden();
});

test('administrator teacher previews match released results and privacy thresholds', function () {
    extract(evaluationScenario());
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 4], 'comment' => 'Helpful feedback'])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get(route('results.index', ['teacher_id' => $teacher->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('results', 0));
    $period->update(['status' => 'released', 'minimum_responses' => 3]);
    $this->get(route('results.index', ['teacher_id' => $teacher->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('results', 0));
    $period->update(['minimum_responses' => 1]);
    $this->get(route('results.index', ['teacher_id' => $teacher->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedTeacher.id', $teacher->id)->has('results', 1)->where('results.0.score', 4)->where('results.0.comments.0', 'Helpful feedback')->missing('results.0.student_id'));
    $other = User::factory()->create();
    $other->assignRole('Teacher');
    $this->get(route('results.index', ['teacher_id' => $other->id]))->assertInertia(fn (Assert $page) => $page->has('results', 0));
});

test('student and teacher portals remain scoped to the signed in account', function () {
    extract(evaluationScenario());

    $this->actingAs($student)->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('adminView', false)->has('students', 0)->where('teachers.0.assignment_id', $assignment->id));
    $this->get(route('evaluations.create', ['period' => $period->id, 'assignment' => $assignment->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('adminView', false)->has('form.criteria', 2));
    $this->get(route('evaluations.index', ['student_id' => $admin->id]))->assertForbidden();
    $this->get(route('evaluations.create', ['period' => $period->id, 'assignment' => $assignment->id, 'student_id' => $admin->id]))->assertForbidden();
    $this->get(route('results.index'))->assertForbidden();
    $this->actingAs($teacher)->get(route('results.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('adminView', false)->has('teachers', 0)->where('selectedTeacher.id', $teacher->id));
    $this->get(route('results.index', ['teacher_id' => $admin->id]))->assertForbidden();
    $this->get(route('evaluations.index'))->assertForbidden();
});

test('administrator portals reject accounts with the wrong role', function () {
    extract(evaluationScenario());

    $this->actingAs($admin)->get(route('evaluations.index', ['student_id' => $teacher->id]))->assertNotFound();
    $this->get(route('results.index', ['teacher_id' => $student->id]))->assertNotFound();
    $this->get(route('evaluations.index', ['student_id' => 'invalid']))->assertSessionHasErrors('student_id');
    $this->get(route('results.index', ['teacher_id' => 'invalid']))->assertSessionHasErrors('teacher_id');
});

test('evaluation portals require a signed in account with the relevant access', function () {
    extract(evaluationScenario());
    $member = User::factory()->create();
    $routes = [
        route('evaluations.index'),
        route('evaluations.create', ['period' => $period->id, 'assignment' => $assignment->id]),
        route('results.index'),
    ];

    foreach ($routes as $url) {
        $this->get($url)->assertRedirectToRoute('login');
    }
    $this->actingAs($member);
    foreach ($routes as $url) {
        $this->get($url)->assertForbidden();
    }
});
