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

function templatePayload(array $overrides = []): array
{
    return [...[
        'name' => 'Teaching evaluation',
        'description' => 'Rate each teaching practice.',
        'scale_min' => 1,
        'scale_max' => 5,
        'is_active' => true,
        'criteria' => [['prompt' => 'Explains clearly', 'weight' => 2]],
        'performance_bands' => [['label' => 'Original performance', 'min' => 1, 'max' => 5]],
    ], ...$overrides];
}

function templateAdmin(): User
{
    test()->seed(DemoAccountSeeder::class);

    return User::where('email', 'admin@edumetric.test')->firstOrFail();
}

function periodPayload(EvaluationForm $form): array
{
    SchoolYear::firstOrCreate(['name' => '2026-2027']);

    return ['name' => 'Semester evaluation', 'school_year' => '2026-2027', 'evaluation_form_id' => $form->id, 'opens_at' => now()->subHour()->toIso8601String(), 'closes_at' => now()->addDay()->toIso8601String(), 'minimum_responses' => 1];
}

function templateEvaluationScenario(): array
{
    $admin = templateAdmin();
    Section::factory()->create(['name' => 'A']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $student->forceFill(['strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A'])->save();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => 11, 'section' => 'A']);
    $form = EvaluationForm::factory()->create();
    $first = $form->criteria()->create(['prompt' => 'Clear explanations', 'weight' => 2, 'position' => 1]);
    $second = $form->criteria()->create(['prompt' => 'Useful feedback', 'weight' => 1, 'position' => 2]);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'school_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'status' => 'open', 'minimum_responses' => 1]);

    return compact('admin', 'teacher', 'student', 'assignment', 'form', 'first', 'second', 'period');
}

test('new templates start at version one and new versions share the original template', function () {
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload(['version' => 90, 'template_id' => 999]))->assertSessionHasNoErrors();
    $original = EvaluationForm::firstOrFail();
    expect($original->version)->toBe(1);
    expect($original->template_id)->toBeNull();

    $this->post(route('forms.store'), templatePayload(['source_form_id' => $original->id, 'criteria' => [['prompt' => 'Updated question', 'weight' => 3]], 'version' => 90]))->assertSessionHasNoErrors();
    $second = EvaluationForm::latest('id')->firstOrFail();
    expect($second->template_id)->toBe($original->id);
    expect($second->version)->toBe(2);
    expect($second->criteria->sole()->prompt)->toBe('Updated question');
    expect($original->fresh()->criteria->sole()->prompt)->toBe('Explains clearly');

    $this->post(route('forms.store'), templatePayload(['source_form_id' => $second->id]))->assertSessionHasNoErrors();
    $third = EvaluationForm::latest('id')->firstOrFail();
    expect($third->template_id)->toBe($original->id);
    expect($third->version)->toBe(3);

    $this->post(route('forms.store'), templatePayload(['source_form_id' => $original->id]))->assertSessionHasNoErrors();
    expect(EvaluationForm::latest('id')->firstOrFail()->version)->toBe(4);
});

test('a superseded template version cannot be edited even before a period uses it', function () {
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload())->assertSessionHasNoErrors();
    $original = EvaluationForm::firstOrFail();
    $this->post(route('forms.store'), templatePayload(['source_form_id' => $original->id]))->assertSessionHasNoErrors();

    $this->put(route('forms.update', $original), templatePayload(['name' => 'Overwritten']))->assertSessionHasErrors('form');
    expect($original->fresh()->name)->toBe('Teaching evaluation');
    $this->get(route('forms.index'))->assertInertia(fn (Assert $page) => $page->where('forms.1.is_locked', true)->where('forms.0.is_locked', false));
});

test('the latest unused version can be corrected without changing its lineage', function () {
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload())->assertSessionHasNoErrors();
    $original = EvaluationForm::firstOrFail();
    $this->post(route('forms.store'), templatePayload(['source_form_id' => $original->id]))->assertSessionHasNoErrors();
    $latest = EvaluationForm::latest('id')->firstOrFail();

    $this->put(route('forms.update', $latest), templatePayload(['name' => 'Corrected', 'template_id' => null, 'version' => 100]))->assertSessionHasNoErrors();
    expect($latest->fresh()->name)->toBe('Corrected');
    expect($latest->fresh()->template_id)->toBe($original->id);
    expect($latest->fresh()->version)->toBe(2);
});

test('new template versions preserve existing questionnaires responses scores and performance bands', function () {
    extract(templateEvaluationScenario());
    $form->update(['performance_bands' => [['label' => 'Original band', 'min' => 1, 'max' => 5]]]);
    $this->actingAs($student)->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 1]])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('forms.store'), templatePayload(['source_form_id' => $form->id, 'scale_max' => 10, 'criteria' => [['prompt' => 'Different question', 'weight' => 10]], 'performance_bands' => [['label' => 'New band', 'min' => 1, 'max' => 10]]]))->assertSessionHasNoErrors();
    $new = EvaluationForm::latest('id')->firstOrFail();
    $this->post(route('periods.store'), periodPayload($new))->assertSessionHasNoErrors();
    expect(EvaluationPeriod::latest('id')->firstOrFail()->evaluation_form_id)->toBe($new->id);
    expect($period->fresh()->evaluation_form_id)->toBe($form->id);
    expect(Evaluation::firstOrFail()->evaluation_form_id)->toBe($form->id);
    expect(Evaluation::firstOrFail()->responses()->orderBy('evaluation_criterion_id')->pluck('evaluation_criterion_id')->all())->toBe([$first->id, $second->id]);

    $this->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment, 'student_id' => $student->id]))->assertInertia(fn (Assert $page) => $page->has('form.criteria', 2)->where('form.criteria.0.prompt', 'Clear explanations')->where('form.scale_max', 5)->where('form.version', 1));
    $this->get(route('reports.index', ['period' => $period->id, 'teacher' => $teacher->id]))->assertInertia(fn (Assert $page) => $page->has('results', 1)->where('results.0.score', 3)->where('results.0.template_version', 1)->where('results.0.scale_max', 5)->where('results.0.performance_level', 'Original band')->where('results.0.criteria.0.prompt', 'Clear explanations')->where('results.0.criteria.0.average', 4));
    $download = $this->get(route('reports.download', ['period' => $period->id, 'teacher' => $teacher->id]))->assertOk()->assertDownload();
    expect($download->streamedContent())->toContain('Template,"Template version"')->toContain($form->name);
    $period->update(['status' => 'released']);
    $this->actingAs($teacher)->get(route('results.index'))->assertInertia(fn (Assert $page) => $page->where('results.0.template_version', 1)->where('results.0.score', 3)->where('results.0.performance_level', 'Original band'));
});

test('retiring a pinned version preserves draft editing and opening', function () {
    $this->freezeTime();
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload())->assertSessionHasNoErrors();
    $form = EvaluationForm::firstOrFail();
    $payload = periodPayload($form);
    $this->post(route('periods.store'), $payload)->assertSessionHasNoErrors();
    $period = EvaluationPeriod::firstOrFail();

    $this->patch(route('forms.status', $form), ['is_active' => false])->assertSessionHasNoErrors();
    expect($form->fresh()->is_active)->toBeFalse();
    $this->post(route('periods.store'), $payload)->assertSessionHasErrors('evaluation_form_id');
    $this->put(route('periods.update', $period), [...$payload, 'name' => 'Updated schedule'])->assertSessionHasNoErrors();
    $this->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasNoErrors();
    expect($period->fresh()->isOpen())->toBeTrue();
    $this->assertDatabaseCount('evaluation_periods', 1);
});

test('retiring a version does not interrupt student submissions to existing periods', function () {
    extract(templateEvaluationScenario());
    $this->actingAs($admin)->patch(route('forms.status', $form), ['is_active' => false, 'name' => 'Changed', 'scale_max' => 10])->assertSessionHasNoErrors();
    expect($form->fresh()->name)->toBe($form->name);
    expect($form->fresh()->scale_max)->toBe(5);

    $this->actingAs($student)->get(route('evaluations.create', ['period' => $period, 'assignment' => $assignment]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('form.version', 1)->has('form.criteria', 2));
    $this->post(route('evaluations.store', $period), ['assignment_id' => $assignment->id, 'ratings' => [$first->id => 4, $second->id => 1]])->assertSessionHasNoErrors();
    expect(Evaluation::firstOrFail()->evaluation_form_id)->toBe($form->id);
    expect((float) Evaluation::firstOrFail()->score)->toBe(3.0);
});

test('periods cannot switch versions once opened', function () {
    extract(templateEvaluationScenario());
    $this->actingAs($admin)->post(route('forms.store'), templatePayload())->assertSessionHasNoErrors();
    $new = EvaluationForm::latest('id')->firstOrFail();

    $this->put(route('periods.update', $period), periodPayload($new))->assertSessionHasErrors('period');
    expect($period->fresh()->evaluation_form_id)->toBe($form->id);
});

test('draft updates reject empty and newly retired replacement versions', function (string $case) {
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload())->assertSessionHasNoErrors();
    $original = EvaluationForm::firstOrFail();
    $this->post(route('periods.store'), periodPayload($original))->assertSessionHasNoErrors();
    $period = EvaluationPeriod::firstOrFail();
    $replacement = EvaluationForm::factory()->create(['is_active' => $case !== 'retired']);
    if ($case === 'retired') {
        $replacement->criteria()->create(['prompt' => 'Question', 'weight' => 1, 'position' => 1]);
    }

    $this->put(route('periods.update', $period), periodPayload($replacement))->assertSessionHasErrors('evaluation_form_id');
    expect($period->fresh()->evaluation_form_id)->toBe($original->id);
})->with(['empty', 'retired']);

test('unknown source versions and unauthorized version creation never save forms', function () {
    $this->actingAs(templateAdmin())->post(route('forms.store'), templatePayload(['source_form_id' => 999999]))->assertSessionHasErrors('source_form_id');
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create();

    $this->actingAs($student)->post(route('forms.store'), templatePayload(['source_form_id' => $form->id]))->assertForbidden();
    $this->patch(route('forms.status', $form), ['is_active' => false])->assertForbidden();
    $this->assertDatabaseCount('evaluation_forms', 1);
    expect($form->fresh()->is_active)->toBeTrue();
});
