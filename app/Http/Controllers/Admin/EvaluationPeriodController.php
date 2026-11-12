<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEvaluationPeriodRequest;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationPeriodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Periods', ['periods' => EvaluationPeriod::with('form:id,name,version')->withCount('evaluations')->orderByDesc('id')->get(), 'forms' => EvaluationForm::with(['criteria' => fn ($query) => $query->orderBy('position')])->whereHas('criteria')->orderBy('name')->orderByDesc('version')->get(['id', 'name', 'version', 'description', 'scale_min', 'scale_max', 'performance_bands', 'is_active']), 'schoolYears' => SchoolYear::orderByDesc('name')->get(['id', 'name'])]);
    }

    public function store(SaveEvaluationPeriodRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $form = EvaluationForm::whereKey($request->integer('evaluation_form_id'))->lockForUpdate()->firstOrFail();
            if (! $form->is_active || ! $form->criteria()->exists()) {
                throw ValidationException::withMessages(['evaluation_form_id' => 'Choose an active template version with questions.']);
            }
            EvaluationPeriod::create([...$request->validated(), 'status' => 'draft']);
        });

        return to_route('periods.index')->with('success', 'Evaluation period created as a draft.');
    }

    public function update(SaveEvaluationPeriodRequest $request, EvaluationPeriod $period): RedirectResponse
    {
        DB::transaction(function () use ($request, $period): void {
            $period = EvaluationPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Only draft periods can be edited.']);
            }
            $form = EvaluationForm::whereKey($request->integer('evaluation_form_id'))->lockForUpdate()->firstOrFail();
            if ((! $form->is_active && $form->id !== $period->evaluation_form_id) || ! $form->criteria()->exists()) {
                throw ValidationException::withMessages(['evaluation_form_id' => 'Choose an active template version with questions.']);
            }
            $period->update($request->validated());
        });

        return to_route('periods.index')->with('success', 'Draft updated.');
    }

    public function transition(Request $request, EvaluationPeriod $period): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['open', 'close', 'release'])]]);
        Gate::authorize($data['action'] === 'release' ? 'results.release' : 'periods.manage');
        DB::transaction(function () use ($period, $data): void {
            $period = EvaluationPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $valid = match ($data['action']) {
                'open' => $period->status === 'draft' && $period->closes_at->gt(now()), 'close' => $period->status === 'open', 'release' => $period->status === 'closed', default => false
            };
            if (! $valid) {
                throw ValidationException::withMessages(['period' => 'That transition is not available for this period.']);
            }
            if ($data['action'] === 'open' && $period->evaluation_form_id === null) {
                throw ValidationException::withMessages(['period' => 'Choose a template version before opening this period.']);
            }
            if ($data['action'] === 'open') {
                EvaluationForm::whereKey($period->evaluation_form_id)->lockForUpdate()->firstOrFail();
            }
            if ($data['action'] === 'open' && ! $period->form->criteria()->exists()) {
                throw ValidationException::withMessages(['period' => 'The pinned template version must have questions.']);
            } $period->update(['status' => match ($data['action']) {
                'open' => 'open','close' => 'closed','release' => 'released'
            }, 'released_at' => $data['action'] === 'release' ? now() : null]);
        });

        return to_route('periods.index')->with('success', 'Evaluation period updated.');
    }
}
