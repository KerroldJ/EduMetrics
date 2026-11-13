<?php

use App\Actions\GenerateReportPdf;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators preview all teacher results within an evaluation period with escaped feedback', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $form = EvaluationForm::factory()->create(['name' => 'Selected questionnaire']);
    $criterion = $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $period = EvaluationPeriod::factory()->create(['name' => 'Selected semester', 'evaluation_form_id' => $form->id, 'status' => 'closed']);
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $evaluation = Evaluation::factory()->create(['teacher_id' => $teacher->id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $period->id, 'score' => 4, 'comment' => '<script>feedback</script>']);
    $evaluation->responses()->create(['evaluation_criterion_id' => $criterion->id, 'rating' => 4]);
    $other = User::factory()->create(['name' => 'Second Teacher']);
    Evaluation::factory()->create(['teacher_id' => $other->id, 'evaluation_period_id' => $period->id, 'evaluation_form_id' => $form->id]);
    $bytes = "%PDF-1.4\nreport preview";
    $this->mock(GenerateReportPdf::class)->shouldReceive('handle')->once()->with(Mockery::on(function (string $html) use ($teacher): bool {
        expect($html)->toContain($teacher->name, 'Selected semester', 'Selected questionnaire', 'Explains clearly', '4.00', 'Provisional', '&lt;script&gt;feedback&lt;/script&gt;');
        expect($html)->toContain('Second Teacher');
        expect($html)->not->toContain('<script>feedback</script>');

        return true;
    }))->andReturn($bytes);
    $filters = ['period' => $period->id];

    $this->actingAs($admin)->get(route('reports.pdf', $filters))->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="edumetric-period-'.$period->id.'-results-'.now()->format('Y-m-d').'.pdf"')->assertContent($bytes);

    $this->get(route('reports.index', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('filters.period', $period->id)->missing('filters.teacher')
        ->has('results', 2)->where('results.0.score', 4)->where('periodLabel', 'Selected semester · 2026-2027')->where('selectedPeriod.id', $period->id)->missing('teacherLabel'));
    $csv = $this->get(route('reports.download', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain($teacher->name, 'Second Teacher', '4.00');
});

test('empty report PDF previews render an explicit empty state', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $period = EvaluationPeriod::factory()->create(['name' => 'Empty semester']);
    $this->mock(GenerateReportPdf::class)->shouldReceive('handle')->once()->with(Mockery::on(function (string $html): bool {
        expect($html)->toContain('No submitted evaluations in this period', 'Empty semester');

        return true;
    }))->andReturn('%PDF-1.4 empty report');

    $this->actingAs($admin)->get(route('reports.pdf', ['period' => $period->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('PDF rendering requires report view generation and download permissions', function (string $role) {
    $this->seed(DemoAccountSeeder::class);
    $user = User::where('email', strtolower($role).'@edumetric.test')->firstOrFail();
    if ($role === 'Admin') {
        $user->syncRoles([]);
        $user->givePermissionTo('reports.download');
    }
    $this->mock(GenerateReportPdf::class)->shouldNotReceive('handle');

    $this->actingAs($user)->get(route('reports.pdf'))->assertForbidden();
})->with(['Teacher', 'Student', 'Admin']);

test('PDF previews require authentication and validate filters before rendering', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $this->mock(GenerateReportPdf::class)->shouldNotReceive('handle');

    $this->get(route('reports.pdf'))->assertRedirectToRoute('login');
    $this->actingAs($admin)->get(route('reports.pdf', ['period' => 999999]))->assertSessionHasErrors('period');
});

test('PDF previews require every report permission independently', function (array $permissions) {
    $this->seed(DemoAccountSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $this->mock(GenerateReportPdf::class)->shouldNotReceive('handle');

    $this->actingAs($user)->get(route('reports.pdf'))->assertForbidden();
})->with([
    'missing view permission' => [['reports.generate', 'reports.download']],
    'missing generation permission' => [['results.view-all', 'reports.download']],
    'missing download permission' => [['results.view-all', 'reports.generate']],
]);
