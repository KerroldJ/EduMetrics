<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEvaluationFormRequest;
use App\Models\EvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationFormController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Forms', ['forms' => EvaluationForm::with(['criteria' => fn ($query) => $query->orderBy('position')])->withCount('periods')->orderByDesc('id')->get()]);
    }

    public function store(SaveEvaluationFormRequest $request): RedirectResponse
    {
        $this->save($request);

        return to_route('forms.index')->with('success', 'Evaluation form created.');
    }

    public function update(SaveEvaluationFormRequest $request, EvaluationForm $form): RedirectResponse
    {
        $this->save($request, $form);

        return to_route('forms.index')->with('success', 'Evaluation form updated.');
    }

    private function save(SaveEvaluationFormRequest $request, ?EvaluationForm $form = null): void
    {
        $data = $request->validated();
        $bands = collect($request->performanceBands())->sortBy('min')->values();
        foreach ($bands as $index => $band) {
            if ($band['min'] > $band['max'] || ($index > 0 && $band['min'] <= $bands[$index - 1]['max'])) {
                throw ValidationException::withMessages(['performance_bands' => 'Performance ranges must be ordered, valid, and non-overlapping.']);
            }
        } DB::transaction(function () use ($form, $data): void {
            if ($form) {
                $form = EvaluationForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
                if ($form->periods()->exists()) {
                    throw ValidationException::withMessages(['form' => 'This form is used by an evaluation period. Duplicate it to create a new version.']);
                } $form->criteria()->delete();
            } else {
                $form = new EvaluationForm;
            } $form->fill(collect($data)->except('criteria')->all())->save();
            foreach ($data['criteria'] as $index => $criterion) {
                $form->criteria()->create(['prompt' => $criterion['prompt'], 'weight' => $criterion['weight'], 'position' => $index + 1]);
            }
        });
    }
}
