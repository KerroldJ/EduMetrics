<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStrandRequest;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StrandController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['tab' => ['nullable', Rule::in(['strands', 'sections', 'school-years'])]]);

        return Inertia::render('admin/Strands', [
            'strands' => Strand::withCount('assignments')->orderBy('code')->get(),
            'sections' => Section::withCount(['users', 'assignments'])->orderBy('name')->get(),
            'schoolYears' => SchoolYear::withCount(['users', 'assignments', 'periods'])->orderByDesc('name')->get(),
            'activeTab' => $filters['tab'] ?? 'strands',
        ]);
    }

    public function store(SaveStrandRequest $request): RedirectResponse
    {
        Strand::create($request->validated());

        return to_route('strands.index')->with('success', 'Strand created.');
    }

    public function update(SaveStrandRequest $request, Strand $strand): RedirectResponse
    {
        $strand->update($request->validated());

        return to_route('strands.index')->with('success', 'Strand updated.');
    }
}
