<?php

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\EvaluationTemplateSeeder;

function availableTemplateData(): array
{
    return ['name' => 'School questionnaire', 'description' => 'Rate observed classroom practices.', 'scale_min' => 1, 'scale_max' => 5, 'criteria' => [['prompt' => 'Explains clearly', 'weight' => 1]]];
}

test('the starter template provides a complete grade eleven and twelve learner feedback instrument', function () {
    $this->seed(EvaluationTemplateSeeder::class);
    $form = EvaluationForm::sole();
    $criteria = $form->criteria()->orderBy('position')->get();

    expect($form->name)->toBe('Grade 11–12 Student Feedback — PPST-aligned');
    expect($form->version)->toBe(1);
    expect($form->is_active)->toBeTrue();
    expect($form->scale_min)->toBe(1);
    expect($form->scale_max)->toBe(5);
    expect($form->description)->toContain('not the official DepEd PMES/IPCRF')->toContain('school-designed')->toContain('Grade 11')->toContain('Grade 12');
    expect($criteria)->toHaveCount(20);
    expect($criteria->pluck('position')->all())->toBe(range(1, 20));
    expect($criteria->pluck('weight')->unique()->all())->toBe(['1.00']);
    expect($criteria->groupBy(fn ($criterion): string => explode(' — ', $criterion->prompt)[0])->map->count()->all())->toBe([
        'Content Knowledge and Pedagogy' => 4,
        'Learning Environment' => 4,
        'Diversity of Learners' => 4,
        'Curriculum and Planning' => 4,
        'Assessment and Reporting' => 4,
    ]);
    $this->assertDatabaseCount('evaluation_periods', 0);
});

test('repeated template seeding preserves school edits availability and existing period references', function () {
    $this->seed(EvaluationTemplateSeeder::class);
    $form = EvaluationForm::sole();
    $form->update(['description' => 'Reviewed by the school.', 'is_active' => false]);
    $form->criteria()->firstOrFail()->update(['prompt' => 'School-reviewed question']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'released']);
    $before = $form->fresh()->toArray();
    $criteriaBefore = $form->criteria()->orderBy('position')->get()->toArray();

    $this->seed(EvaluationTemplateSeeder::class);

    $this->assertDatabaseCount('evaluation_forms', 1);
    expect($form->fresh()->toArray())->toBe($before);
    expect($form->criteria()->orderBy('position')->get()->toArray())->toBe($criteriaBefore);
    expect($period->fresh()->evaluation_form_id)->toBe($form->id);
});

test('normal database seeding installs the starter template without creating evaluation periods', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('evaluation_forms', ['name' => 'Grade 11–12 Student Feedback — PPST-aligned']);
    $this->assertDatabaseCount('evaluation_forms', 1);
    $this->assertDatabaseCount('evaluation_criteria', 20);
    $this->assertDatabaseCount('evaluation_periods', 0);
});

test('new templates are available without an availability checkbox value', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('forms.store'), availableTemplateData())->assertSessionHasNoErrors();
    expect(EvaluationForm::sole()->is_active)->toBeTrue();
});

test('editing a retired template without an availability value preserves its retirement', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create(['is_active' => false]);

    $this->actingAs($admin)->put(route('forms.update', $form), availableTemplateData())->assertSessionHasNoErrors();
    expect($form->fresh()->is_active)->toBeFalse();
    expect($form->fresh()->name)->toBe('School questionnaire');
});

test('new versions of retired templates are available without changing the retired version', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $original = EvaluationForm::factory()->create(['is_active' => false]);

    $this->actingAs($admin)->post(route('forms.store'), [...availableTemplateData(), 'source_form_id' => $original->id])->assertSessionHasNoErrors();
    $next = EvaluationForm::latest('id')->firstOrFail();
    expect($next->is_active)->toBeTrue();
    expect($next->version)->toBe(2);
    expect($original->fresh()->is_active)->toBeFalse();
});

test('starter template summaries keep numeric scores rounded to two decimal places', function (float $score, float $expected) {
    $this->seed(EvaluationTemplateSeeder::class);
    $form = EvaluationForm::sole();
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'released']);
    $assignment = TeacherAssignment::factory()->create();
    Evaluation::factory()->create(['teacher_id' => $assignment->teacher_id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'score' => $score]);

    $result = Evaluation::summaries($period->id)[0];

    expect($result['score'])->toBe($expected);
    expect($result)->not->toHaveKey('performance_level');
    expect($result['scale_max'])->toBe(5);
})->with([
    'minimum' => [1.0, 1.0],
    'round down near one' => [1.494, 1.49],
    'round up near one' => [1.496, 1.5],
    'round down near two' => [2.494, 2.49],
    'round up near two' => [2.496, 2.5],
    'round down near three' => [3.494, 3.49],
    'round up near three' => [3.496, 3.5],
    'round down near four' => [4.494, 4.49],
    'round up near four' => [4.496, 4.5],
    'maximum' => [5.0, 5.0],
]);
