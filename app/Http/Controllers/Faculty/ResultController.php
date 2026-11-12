<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
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
            'teachers' => $adminView ? User::role('Teacher')->orderBy('name')->get(['id', 'name', 'school_id']) : [],
            'selectedTeacher' => $teacher?->only(['id', 'name']),
            'results' => $teacher ? Evaluation::summaries(teacherId: $teacher->id, releasedOnly: true) : [],
        ]);
    }
}
