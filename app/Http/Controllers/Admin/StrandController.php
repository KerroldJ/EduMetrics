<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStrandRequest;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StrandController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['tab' => ['nullable', Rule::in(['strands', 'sections', 'school-years', 'assignments'])]]);
        $canManageStrands = $request->user()->can('strands.manage');
        $canManageAssignments = $request->user()->can('assignments.manage');
        $availableTabs = [
            ...($canManageStrands ? ['school-years', 'strands', 'sections'] : []),
            ...($canManageAssignments ? ['assignments'] : []),
        ];
        $activeTab = $filters['tab'] ?? ($availableTabs[0] ?? null);
        abort_unless(in_array($activeTab, $availableTabs, true), 403);

        $props = ['activeTab' => $activeTab, 'availableTabs' => $availableTabs];
        if ($canManageStrands) {
            $props += [
                'strands' => Strand::withCount('assignments')->orderBy('code')->get(),
                'sections' => Section::withCount(['users', 'assignments'])->orderBy('name')->get(),
                'schoolYears' => SchoolYear::withCount(['users', 'assignments', 'periods'])->orderByDesc('name')->get(),
            ];
        }
        if ($canManageAssignments) {
            $props += [
                'assignments' => TeacherAssignment::with(['teacher:id,name', 'strand:id,name,code'])
                    ->orderByDesc('id')->paginate(20)->withQueryString()->appends(['tab' => 'assignments']),
                'assignmentOptions' => [
                    'teachers' => User::where('is_active', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher'))->orderBy('name')->get(['id', 'name']),
                    'strands' => Strand::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
                    'sections' => Section::orderBy('name')->get(['id', 'name']),
                    'schoolYears' => SchoolYear::orderByDesc('name')->get(['id', 'name']),
                ],
            ];
        }

        return Inertia::render('admin/Strands', $props);
    }

    public function store(SaveStrandRequest $request): RedirectResponse
    {
        Strand::create($request->validated());

        return to_route('strands.index', ['tab' => 'strands'])->with('success', 'Strand created.');
    }

    public function update(SaveStrandRequest $request, Strand $strand): RedirectResponse
    {
        $strand->update($request->validated());

        return to_route('strands.index', ['tab' => 'strands'])->with('success', 'Strand updated.');
    }
}
