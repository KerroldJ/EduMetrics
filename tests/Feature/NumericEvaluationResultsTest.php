<?php

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('removing classification data preserves linked templates questions responses and numeric scores', function () {
    expect(Schema::hasColumn('evaluation_forms', 'performance_bands'))->toBeFalse();
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $form = EvaluationForm::factory()->create();
    $criterion = $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $assignment = TeacherAssignment::factory()->create();
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id]);
    $evaluation = Evaluation::factory()->create(['teacher_id' => $assignment->teacher_id, 'teacher_assignment_id' => $assignment->id, 'evaluation_period_id' => $period->id, 'evaluation_form_id' => $form->id, 'score' => 4]);
    $response = $evaluation->responses()->create(['evaluation_criterion_id' => $criterion->id, 'rating' => 4]);
    $migration = require database_path('migrations/2026_11_13_123042_drop_performance_bands_from_evaluation_forms_table.php');
    $migration->down();
    DB::table('evaluation_forms')->where('id', $form->id)->update([
        'performance_bands' => json_encode([['label' => 'Old label', 'min' => 1, 'max' => 5]]),
        'description' => 'Original instructions. Survey bands apply to the displayed two-decimal mean. Numeric scores only.',
    ]);

    $migration->up();

    expect(Schema::hasColumn('evaluation_forms', 'performance_bands'))->toBeFalse();
    expect($form->fresh()->description)->toBe('Original instructions. Numeric scores only.');
    expect($period->fresh()->evaluation_form_id)->toBe($form->id);
    expect($evaluation->fresh()->evaluation_form_id)->toBe($form->id);
    expect((float) $evaluation->fresh()->score)->toBe(4.0);
    expect($response->fresh()->evaluation_criterion_id)->toBe($criterion->id);
    expect((float) $response->fresh()->rating)->toBe(4.0);
    expect($criterion->fresh()->prompt)->toBe('Explains clearly');
});

test('reports exports and released teacher results contain numeric scores without classifications', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create();
    $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'released', 'minimum_responses' => 1]);
    Evaluation::factory()->create(['teacher_id' => $teacher->id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'score' => 3.25]);

    $this->actingAs($admin)->get(route('reports.index', ['period' => $period->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.0.score', 3.25)->where('results.0.scale_max', 5)->missing('results.0.performance_level'));
    $this->get(route('forms.index'))->assertInertia(fn (Assert $page) => $page->missing('forms.0.performance_bands'));
    $this->get(route('periods.index'))->assertInertia(fn (Assert $page) => $page->missing('forms.0.performance_bands'));
    $download = $this->get(route('reports.download', ['period' => $period->id]))->assertOk()->assertDownload();
    $rows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), explode("\n", trim($download->streamedContent())));
    expect($rows[0])->toBe(['Teacher', 'Evaluation period', 'School year', 'Status', 'Responses', 'Score', 'Maximum', 'Template', 'Template version']);
    expect($rows[1])->toHaveCount(9);
    expect($rows[1][5])->toBe('3.25');
    $this->actingAs($teacher)->get(route('results.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.0.score', 3.25)->missing('results.0.performance_level'));
});

test('obsolete classification input has no effect when saving a questionnaire', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('forms.store'), [
        'name' => 'Numeric feedback', 'scale_min' => 1, 'scale_max' => 5,
        'criteria' => [['prompt' => 'Explains clearly', 'weight' => 1]], 'performance_bands' => 'obsolete',
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('forms.index');

    $form = EvaluationForm::sole();
    expect($form->criteria->sole()->prompt)->toBe('Explains clearly');
    expect($form->getAttributes())->not->toHaveKey('performance_bands');
});
