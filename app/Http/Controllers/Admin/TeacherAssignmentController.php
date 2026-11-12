<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTeacherAssignmentRequest;
use App\Models\Evaluation;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAssignmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Assignments', ['assignments' => TeacherAssignment::with(['teacher:id,name', 'strand:id,name,code'])->orderByDesc('id')->paginate(20), 'teachers' => User::where('is_active', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher'))->orderBy('name')->get(['id', 'name']), 'strands' => Strand::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']), 'sections' => Section::orderBy('name')->get(['id', 'name']), 'schoolYears' => SchoolYear::orderByDesc('name')->get(['id', 'name'])]);
    }

    public function store(SaveTeacherAssignmentRequest $request): RedirectResponse
    {
        $this->save($request);

        return to_route('assignments.index')->with('success', 'Teacher assigned.');
    }

    public function update(SaveTeacherAssignmentRequest $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->save($request, $assignment);

        return to_route('assignments.index')->with('success', 'Assignment updated.');
    }

    private function save(SaveTeacherAssignmentRequest $request, ?TeacherAssignment $assignment = null): void
    {
        DB::transaction(function () use ($request, $assignment): void {
            $teacher = User::whereKey($request->integer('teacher_id'))->sharedLock()->firstOrFail();
            if (! $teacher->hasRole('Teacher') || ! $teacher->is_active) {
                throw ValidationException::withMessages(['teacher_id' => 'Choose an active Teacher account.']);
            } if ($assignment) {
                $assignment = TeacherAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                $hasResponses = Evaluation::where('teacher_assignment_id', $assignment->id)->exists();
                if ($hasResponses && collect($request->safe()->except('is_active'))->contains(fn ($value, $key): bool => (string) $assignment->getAttribute($key) !== (string) $value)) {
                    throw ValidationException::withMessages(['assignment' => 'This assignment has submitted evaluations. Deactivate it and create a new assignment instead.']);
                }
            } ($assignment ?? new TeacherAssignment)->fill($request->validated())->save();
        });
    }

    public function status(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $assignment): void {
            $assignment = TeacherAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($request->boolean('is_active') && (! $assignment->teacher->is_active || ! $assignment->teacher->hasRole('Teacher') || ! $assignment->strand->is_active)) {
                throw ValidationException::withMessages(['assignment' => 'Reactivate the teacher and strand before reactivating this assignment.']);
            }
            $assignment->update(['is_active' => $request->boolean('is_active')]);
        });

        return to_route('assignments.index')->with('success', 'Assignment status updated.');
    }
}
