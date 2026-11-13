<?php

use App\Actions\GenerateReportPdf;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Inertia\Testing\AssertableInertia as Assert;

test('reports start with evaluation periods including those without submissions', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create();
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id]);

    $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('periods', 1)->where('periods.0.id', $period->id)->where('periods.0.evaluations_count', 0)
        ->where('periods.0.form.name', $form->name)->where('selectedPeriod', null)
        ->has('results', 0)->missing('teachers')->missing('teacherLabel'));
});

test('a period report includes every evaluated teacher in that period and isolates details and exports from other periods', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create();
    $criterion = $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $period = EvaluationPeriod::factory()->create(['name' => 'Selected period', 'evaluation_form_id' => $form->id]);
    $otherPeriod = EvaluationPeriod::factory()->create(['name' => 'Excluded period', 'evaluation_form_id' => $form->id]);
    $first = User::factory()->create(['name' => 'First Teacher']);
    $second = User::factory()->create(['name' => 'Second Teacher']);
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $first->id]);
    $secondAssignment = TeacherAssignment::factory()->create(['teacher_id' => $second->id]);
    $one = Evaluation::factory()->create(['teacher_id' => $first->id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'score' => 4, 'comment' => 'First feedback']);
    $two = Evaluation::factory()->create(['teacher_id' => $second->id, 'teacher_assignment_id' => $secondAssignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'score' => 2, 'comment' => 'Second feedback']);
    $one->responses()->create(['evaluation_criterion_id' => $criterion->id, 'rating' => 4]);
    $two->responses()->create(['evaluation_criterion_id' => $criterion->id, 'rating' => 2]);
    Evaluation::factory()->create(['teacher_id' => $first->id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $otherPeriod->id, 'score' => 1, 'comment' => 'Excluded feedback']);
    $query = ['period' => $period->id, 'teacher' => $first->id];

    $this->actingAs($admin)->get(route('reports.index', $query))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedPeriod.id', $period->id)->where('filters.period', $period->id)->missing('filters.teacher')
        ->has('results', 2)->where('results.0.teacher_id', $first->id)->where('results.0.score', 4)
        ->where('results.0.criteria.0.average', 4)->where('results.0.comments', ['First feedback'])
        ->where('results.1.teacher_id', $second->id)->where('results.1.criteria.0.average', 2)->where('results.1.comments', ['Second feedback']));
    $csv = $this->get(route('reports.download', $query))->assertOk()->streamedContent();
    expect($csv)->toContain('First Teacher', 'Second Teacher', 'Selected period')->not->toContain('Excluded period');
    $this->mock(GenerateReportPdf::class)->shouldReceive('handle')->once()->with(Mockery::on(function (string $html): bool {
        expect($html)->toContain('First Teacher', 'Second Teacher', 'First feedback', 'Second feedback')->not->toContain('Excluded period', 'Excluded feedback');

        return true;
    }))->andReturn('%PDF-1.4 period report');
    $this->get(route('reports.pdf', $query))->assertOk();
});

test('exports require an explicit evaluation period', function (string $route) {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $this->mock(GenerateReportPdf::class)->shouldNotReceive('handle');

    $this->actingAs($admin)->get(route($route))->assertSessionHasErrors('period');
})->with(['reports.pdf', 'reports.download']);

test('period details keep the feedback limit separate for each teacher', function () {
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $form = EvaluationForm::factory()->create();
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id]);
    $first = TeacherAssignment::factory()->create();
    $second = TeacherAssignment::factory()->create();
    Evaluation::factory()->count(101)->sequence(fn (Sequence $sequence): array => ['comment' => sprintf('Feedback %03d', $sequence->index)])
        ->create(['teacher_id' => $first->teacher_id, 'teacher_assignment_id' => $first->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id]);
    Evaluation::factory()->create(['teacher_id' => $second->teacher_id, 'teacher_assignment_id' => $second->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'comment' => 'Second teacher feedback']);

    $results = Evaluation::summaries(periodId: $period->id, includeDetails: true);

    expect($results[0]['count'])->toBe(101);
    expect($results[0]['comments'])->toHaveCount(100);
    expect($results[0]['comments'][0])->toBe('Feedback 000');
    expect($results[0]['comments'][99])->toBe('Feedback 099');
    expect($results[1]['comments'])->toBe(['Second teacher feedback']);
});
