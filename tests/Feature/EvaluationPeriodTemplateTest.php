<?php

use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function templateFirstAdmin(): User
{
    test()->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);

    return User::where('email', 'admin@edumetric.test')->firstOrFail();
}

function scheduledPeriodData(): array
{
    return ['name' => 'First semester', 'school_year' => '2026-2027', 'opens_at' => now()->subHour()->toIso8601String(), 'closes_at' => now()->addDay()->toIso8601String(), 'minimum_responses' => 3];
}

function independentTemplateData(): array
{
    return ['name' => 'Teaching questionnaire', 'description' => 'Rate each practice.', 'scale_min' => 1, 'scale_max' => 5, 'is_active' => true, 'criteria' => [['prompt' => 'Explains clearly', 'weight' => 1]], 'performance_bands' => []];
}

test('administrators create a template first then assign it when creating a period', function () {
    $this->freezeTime();
    $this->actingAs(templateFirstAdmin())->post(route('forms.store'), independentTemplateData())->assertSessionHasNoErrors();
    $form = EvaluationForm::firstOrFail();
    expect($form->criteria->sole()->prompt)->toBe('Explains clearly');
    $this->assertDatabaseCount('evaluation_periods', 0);
    $this->get(route('forms.index'))->assertInertia(fn (Assert $page) => $page->has('forms', 1)->where('forms.0.periods_count', 0)->where('forms.0.is_locked', false));

    $this->post(route('periods.store'), [...scheduledPeriodData(), 'evaluation_form_id' => $form->id])->assertSessionHasNoErrors();
    $period = EvaluationPeriod::firstOrFail();
    expect($period->evaluation_form_id)->toBe($form->id);
    expect($period->status)->toBe('draft');
    $this->get(route('periods.index'))->assertInertia(fn (Assert $page) => $page->where('periods.0.form.id', $form->id)->where('periods.0.form.version', 1));
    $this->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasNoErrors();
    expect($period->fresh()->status)->toBe('open');
});

test('period creation requires an existing active template with questions', function (string $case) {
    $this->actingAs(templateFirstAdmin());
    $form = EvaluationForm::factory()->create(['is_active' => $case !== 'retired']);
    if ($case === 'retired') {
        $form->criteria()->create(['prompt' => 'Question', 'weight' => 1, 'position' => 1]);
    }
    $payload = [...scheduledPeriodData(), 'evaluation_form_id' => match ($case) {
        'missing' => null, 'unknown' => 999999, default => $form->id
    }];

    $this->post(route('periods.store'), $payload)->assertSessionHasErrors('evaluation_form_id');
    $this->assertDatabaseCount('evaluation_periods', 0);
})->with(['missing', 'unknown', 'empty', 'retired']);

test('saving a template never assigns it to a period supplied as an extra field', function () {
    $this->actingAs(templateFirstAdmin());
    $period = EvaluationPeriod::factory()->create(['status' => 'open']);
    $originalId = $period->evaluation_form_id;

    $this->post(route('forms.store'), [...independentTemplateData(), 'evaluation_period_id' => $period->id])->assertSessionHasNoErrors();
    $form = EvaluationForm::latest('id')->firstOrFail();
    expect($period->fresh()->evaluation_form_id)->toBe($originalId);
    expect($form->periods()->count())->toBe(0);
    $this->assertDatabaseCount('evaluation_periods', 1);
});

test('new template versions can be prepared before a new period exists', function () {
    $this->actingAs(templateFirstAdmin())->post(route('forms.store'), independentTemplateData())->assertSessionHasNoErrors();
    $original = EvaluationForm::firstOrFail();
    $this->post(route('periods.store'), [...scheduledPeriodData(), 'evaluation_form_id' => $original->id])->assertSessionHasNoErrors();
    $period = EvaluationPeriod::firstOrFail();
    $this->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasNoErrors();

    $this->post(route('forms.store'), [...independentTemplateData(), 'source_form_id' => $original->id, 'criteria' => [['prompt' => 'Updated question', 'weight' => 2]]])->assertSessionHasNoErrors();
    $next = EvaluationForm::latest('id')->firstOrFail();
    expect($next->version)->toBe(2);
    expect($next->periods()->count())->toBe(0);
    expect($period->fresh()->evaluation_form_id)->toBe($original->id);
    expect($original->fresh()->criteria->sole()->prompt)->toBe('Explains clearly');
    $this->assertDatabaseCount('evaluation_periods', 1);
});

test('existing unassigned drafts require a template before opening or saving changes', function () {
    $this->actingAs(templateFirstAdmin());
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => null]);
    $this->get(route('periods.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('periods.0.form', null));
    $this->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasErrors('period');
    $this->put(route('periods.update', $period), scheduledPeriodData())->assertSessionHasErrors('evaluation_form_id');
    $this->post(route('forms.store'), independentTemplateData())->assertSessionHasNoErrors();
    $form = EvaluationForm::firstOrFail();

    $this->put(route('periods.update', $period), [...scheduledPeriodData(), 'evaluation_form_id' => $form->id])->assertSessionHasNoErrors();
    $this->post(route('periods.transition', $period), ['action' => 'open'])->assertSessionHasNoErrors();
    expect($period->fresh()->evaluation_form_id)->toBe($form->id);
    expect($period->fresh()->status)->toBe('open');
});

test('invalid template questions never partially save a standalone template', function () {
    $this->actingAs(templateFirstAdmin())->post(route('forms.store'), [...independentTemplateData(), 'criteria' => []])->assertSessionHasErrors('criteria');
    $this->assertDatabaseCount('evaluation_forms', 0);
    $this->assertDatabaseCount('evaluation_periods', 0);
});

test('template management permission allows preparing templates without period management access', function () {
    templateFirstAdmin();
    $manager = User::factory()->create();
    $manager->givePermissionTo('forms.manage');

    $this->actingAs($manager)->post(route('forms.store'), independentTemplateData())->assertSessionHasNoErrors();
    $form = EvaluationForm::firstOrFail();
    $this->post(route('periods.store'), [...scheduledPeriodData(), 'evaluation_form_id' => $form->id])->assertForbidden();
    $this->assertDatabaseCount('evaluation_periods', 0);
    expect($form->criteria->sole()->prompt)->toBe('Explains clearly');
});
