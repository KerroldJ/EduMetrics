<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function index(Request $request): Response
    {
        $adminView = $request->user()->can('results.view-all');
        $teacher = $request->user();

        if ($adminView) {
            $request->validate(['teacher_id' => ['nullable', 'integer']]);
            $teacher = $request->filled('teacher_id')
                ? User::role('Teacher')->findOrFail($request->integer('teacher_id'))
                : null;
        } else {
            abort_if($request->has('teacher_id'), 403);
        }

        return Inertia::render('faculty/Results', [
            'adminView' => $adminView,
            'teachers' => $adminView ? $this->teacherProfiles() : [],
            'selectedTeacher' => $teacher?->only(['id', 'name', 'avatar']),
            'results' => $teacher ? Evaluation::summaries(teacherId: $teacher->id, releasedOnly: true) : [],
        ]);
    }

    /** @return array<int, array{id: int, name: string, school_id: string|null, avatar: string|null, evaluated_students_count: int, assignments: array<int, array{id: int, strand: array{code: string, name: string}, school_year: string, grade_level: int|null, section: string|null}>}> */
    private function teacherProfiles(): array
    {
        $teachers = User::role('Teacher')->select(['id', 'name', 'school_id', 'avatar_path'])
            ->addSelect(['evaluated_students_count' => Evaluation::selectRaw('COUNT(DISTINCT student_id)')
                ->whereColumn('teacher_id', 'users.id')->whereNotNull('submitted_at')])
            ->orderBy('name')->get();
        $assignments = TeacherAssignment::whereIn('teacher_id', $teachers->modelKeys())
            ->where('is_active', true)->whereHas('strand', fn ($query) => $query->where('is_active', true))
            ->with('strand:id,code,name')->orderByDesc('school_year')->orderBy('strand_id')->orderBy('section')
            ->get(['id', 'teacher_id', 'strand_id', 'school_year', 'grade_level', 'section'])->groupBy('teacher_id');

        return $teachers->map(fn (User $teacher): array => [
            'id' => (int) $teacher->id,
            'name' => $teacher->name,
            'school_id' => $teacher->school_id,
            'avatar' => $teacher->avatar,
            'evaluated_students_count' => (int) $teacher->getAttribute('evaluated_students_count'),
            'assignments' => $assignments->get($teacher->id, collect())->map(fn (TeacherAssignment $assignment): array => [
                'id' => (int) $assignment->id,
                'strand' => ['code' => $assignment->strand->code, 'name' => $assignment->strand->name],
                'school_year' => $assignment->school_year,
                'grade_level' => $assignment->grade_level,
                'section' => $assignment->section,
            ])->values()->all(),
        ])->all();
    }
}
