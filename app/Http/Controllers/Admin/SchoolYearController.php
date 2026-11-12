<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSchoolYearRequest;
use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolYearController extends Controller
{
    public function store(SaveSchoolYearRequest $request): RedirectResponse
    {
        SchoolYear::create($request->validated());

        return to_route('strands.index', ['tab' => 'school-years'])->with('success', 'School year created.');
    }

    public function update(SaveSchoolYearRequest $request, SchoolYear $schoolYear): RedirectResponse
    {
        $schoolYear->update($request->validated());

        return to_route('strands.index', ['tab' => 'school-years'])->with('success', 'School year updated.');
    }

    public function destroy(SchoolYear $schoolYear): RedirectResponse
    {
        DB::transaction(function () use ($schoolYear): void {
            $schoolYear = SchoolYear::whereKey($schoolYear->id)->lockForUpdate()->firstOrFail();
            if ($schoolYear->users()->exists() || $schoolYear->assignments()->exists() || $schoolYear->periods()->exists()) {
                throw ValidationException::withMessages(['school_year' => 'This school year is in use. Reassign its enrollment, teacher assignments, and evaluation periods before deleting it.']);
            }
            $schoolYear->delete();
        });

        return to_route('strands.index', ['tab' => 'school-years'])->with('success', 'School year deleted.');
    }
}
