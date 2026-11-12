<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSectionRequest;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function store(SaveSectionRequest $request): RedirectResponse
    {
        Section::create($request->validated());

        return to_route('strands.index', ['tab' => 'sections'])->with('success', 'Section created.');
    }

    public function update(SaveSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return to_route('strands.index', ['tab' => 'sections'])->with('success', 'Section updated.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        DB::transaction(function () use ($section): void {
            $section = Section::whereKey($section->id)->lockForUpdate()->firstOrFail();
            if ($section->users()->exists() || $section->assignments()->exists()) {
                throw ValidationException::withMessages(['section' => 'This section is in use. Reassign its accounts and teacher assignments before deleting it.']);
            }
            $section->delete();
        });

        return to_route('strands.index', ['tab' => 'sections'])->with('success', 'Section deleted.');
    }
}
