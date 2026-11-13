<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEvaluationFormRequest;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationFormController extends Controller
{
    public function index(): Response
    {
        $forms = EvaluationForm::with(['criteria' => fn ($query) => $query->orderBy('position'), 'periods:id,name,school_year,status,evaluation_form_id'])->withCount('periods')->orderByDesc('id')->get();
        $latestVersions = $forms->groupBy(fn (EvaluationForm $form): int => $form->template_id ?? $form->id)->map(fn ($versions): int => (int) $versions->max('version'));

        return Inertia::render('admin/Forms', ['forms' => $forms->map(fn (EvaluationForm $form): array => [...$form->toArray(), 'is_locked' => $form->periods->contains(fn (EvaluationPeriod $period): bool => $period->status !== 'draft') || $form->version < $latestVersions[$form->template_id ?? $form->id]])]);
    }

    public function store(SaveEvaluationFormRequest $request): RedirectResponse
    {
        $this->save($request);

        return to_route('forms.index')->with('success', $request->filled('source_form_id') ? 'New template version created. Existing evaluations keep their original version.' : 'Evaluation template created as version 1.');
    }

    public function update(SaveEvaluationFormRequest $request, EvaluationForm $form): RedirectResponse
    {
        $this->save($request, $form);

        return to_route('forms.index')->with('success', 'Draft template version updated.');
    }

    public function status(Request $request, EvaluationForm $form): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $form->update($data);

        return to_route('forms.index')->with('success', $data['is_active'] ? 'Version available for new periods.' : 'Version retired from new periods. Existing periods and results are preserved.');
    }

    private function save(SaveEvaluationFormRequest $request, ?EvaluationForm $form = null): void
    {
        $data = $request->validated();
        DB::transaction(function () use ($form, $data): void {
            if ($form) {
                EvaluationForm::whereKey($form->template_id ?? $form->id)->lockForUpdate()->firstOrFail();
                $form = EvaluationForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
                if ($form->isLocked()) {
                    throw ValidationException::withMessages(['form' => 'This version is locked because an opened period or newer version uses it. Create a new version to make changes.']);
                }
                $form->criteria()->delete();
            } else {
                $form = new EvaluationForm;
                $form->version = 1;
                $form->is_active = true;
                if (! empty($data['source_form_id'])) {
                    $source = EvaluationForm::whereKey($data['source_form_id'])->firstOrFail();
                    $templateId = $source->template_id ?? $source->id;
                    EvaluationForm::whereKey($templateId)->lockForUpdate()->firstOrFail();
                    $form->template_id = $templateId;
                    $form->version = max(1, (int) EvaluationForm::where('template_id', $templateId)->max('version')) + 1;
                }
            }

            $form->fill(collect($data)->except(['criteria', 'source_form_id'])->all())->save();
            foreach ($data['criteria'] as $index => $criterion) {
                $form->criteria()->create(['prompt' => $criterion['prompt'], 'weight' => $criterion['weight'], 'position' => $index + 1]);
            }
        });
    }
}
