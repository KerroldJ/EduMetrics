<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitEvaluationRequest;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationResponse;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function index(Request $request): Response
    {
        $adminView = $request->user()->can('evaluations.monitor');
        $user = $this->studentForView($request);
        $teachers = [];
        if ($user) {
            $periods = EvaluationPeriod::where('school_year', $user->school_year)
                ->where('status', 'open')->where('opens_at', '<=', now())->where('closes_at', '>', now())
                ->whereNotNull('evaluation_form_id')->orderByDesc('id')->get(['id', 'name']);
            $submitted = Evaluation::where('student_id', $user->id)->whereIn('evaluation_period_id', $periods->modelKeys())
                ->get(['teacher_id', 'evaluation_period_id'])->keyBy(fn (Evaluation $evaluation): string => $evaluation->teacher_id.':'.$evaluation->evaluation_period_id);
            $teachers = $user->eligibleAssignments()->with('teacher:id,name')->orderBy('id')->get()
                ->unique('teacher_id')->sortBy('teacher.name')->values()->map(fn (TeacherAssignment $assignment): array => [
                    'id' => (int) $assignment->teacher_id,
                    'name' => $assignment->teacher->name,
                    'assignment_id' => (int) $assignment->id,
                    'evaluations' => $periods->map(fn (EvaluationPeriod $period): array => [
                        'period_id' => $period->id,
                        'period' => $period->name,
                        'submitted' => $submitted->has($assignment->teacher_id.':'.$period->id),
                    ])->all(),
                ])->all();
        }

        return Inertia::render('student/Evaluations', [
            'adminView' => $adminView,
            'students' => $adminView ? User::role('Student')->orderBy('name')->get(['id', 'name', 'school_id']) : [],
            'selectedStudent' => $user?->only(['id', 'name']),
            'teachers' => $teachers,
            'strand' => $user?->strand?->only(['id', 'name', 'code']),
            'enrollment' => $user?->only(['school_year', 'grade_level', 'section']) ?? ['school_year' => null, 'grade_level' => null, 'section' => null],
        ]);
    }

    public function create(Request $request, EvaluationPeriod $period, TeacherAssignment $assignment): Response|RedirectResponse
    {
        $adminView = $request->user()->can('evaluations.monitor');
        $student = $this->studentForView($request);
        abort_unless($student !== null, 404, 'Select a student to preview their evaluation.');
        Gate::forUser($student)->authorize('submit', [Evaluation::class, $period, $assignment]);
        abort_unless($period->isOpen(), 403, 'This evaluation period is not open.');
        if (! $adminView && Evaluation::where('student_id', $student->id)->where('teacher_id', $assignment->teacher_id)->where('evaluation_period_id', $period->id)->exists()) {
            return to_route('evaluations.index')->with('success', 'You have already evaluated this teacher.');
        }

        return Inertia::render('student/Evaluate', ['adminView' => $adminView, 'selectedStudent' => $student->only(['id', 'name']), 'period' => $period->only(['id', 'name', 'closes_at']), 'assignment' => ['id' => $assignment->id, 'teacher' => $assignment->teacher->name], 'form' => [...$period->form->only(['name', 'version', 'description', 'scale_min', 'scale_max']), 'criteria' => $period->form->criteria()->orderBy('position')->get(['id', 'prompt', 'weight'])]]);
    }

    public function store(SubmitEvaluationRequest $request, EvaluationPeriod $period): RedirectResponse
    {
        DB::transaction(function () use ($request, $period): void {
            $period = EvaluationPeriod::whereKey($period->id)->sharedLock()->firstOrFail();
            $student = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $assignment = TeacherAssignment::whereKey($request->integer('assignment_id'))->sharedLock()->firstOrFail();
            User::whereKey($assignment->teacher_id)->sharedLock()->firstOrFail();
            Strand::whereKey($assignment->strand_id)->sharedLock()->firstOrFail();
            Gate::forUser($student)->authorize('submit', [Evaluation::class, $period, $assignment]);
            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['evaluation' => 'This evaluation period is not open.']);
            }
            if (Evaluation::where('student_id', $student->id)->where('teacher_id', $assignment->teacher_id)->where('evaluation_period_id', $period->id)->exists()) {
                throw ValidationException::withMessages(['evaluation' => 'You have already evaluated this teacher for this period.']);
            }
            $criteria = $period->form->criteria()->orderBy('position')->get();
            $ratings = $request->ratings();
            $ids = array_map('strval', array_keys($ratings));
            if ($criteria->isEmpty() || count($ratings) !== $criteria->count() || array_diff(array_map('strval', $criteria->modelKeys()), $ids) || collect($ratings)->contains(fn ($rating): bool => $rating < $period->form->scale_min || $rating > $period->form->scale_max)) {
                throw ValidationException::withMessages(['ratings' => 'Answer every question using the form’s rating scale.']);
            }
            $weighted = $criteria->sum(fn ($criterion): float => $ratings[$criterion->id] * (float) $criterion->weight);
            $score = round($weighted / (float) $criteria->sum('weight'), 4);
            $evaluation = Evaluation::create(['student_id' => $student->id, 'teacher_id' => $assignment->teacher_id, 'teacher_assignment_id' => $assignment->id, 'evaluation_period_id' => $period->id, 'evaluation_form_id' => $period->evaluation_form_id, 'score' => $score, 'comment' => $request->validated('comment'), 'context' => ['strand_id' => $student->strand_id, 'strand' => $student->strand->code, 'school_year' => $student->school_year, 'grade_level' => $student->grade_level, 'section' => $student->section], 'submitted_at' => now()]);
            foreach ($criteria as $criterion) {
                EvaluationResponse::create(['evaluation_id' => $evaluation->id, 'evaluation_criterion_id' => $criterion->id, 'rating' => $ratings[$criterion->id]]);
            }
        });

        return to_route('evaluations.index')->with('success', 'Your evaluation has been submitted. Thank you for your feedback.');
    }

    private function studentForView(Request $request): ?User
    {
        if (! $request->user()->can('evaluations.monitor')) {
            abort_if($request->has('student_id'), 403);

            return $request->user();
        }

        $request->validate(['student_id' => ['nullable', 'integer']]);

        return $request->filled('student_id')
            ? User::role('Student')->findOrFail($request->integer('student_id'))
            : null;
    }
}
