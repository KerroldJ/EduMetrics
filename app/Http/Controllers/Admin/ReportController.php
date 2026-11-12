<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** @return array{period?: int|null, teacher?: int|null} */
    private function filters(Request $request): array
    {
        return $request->validate(['period' => ['nullable', 'integer', 'exists:evaluation_periods,id'], 'teacher' => ['nullable', 'integer', 'exists:users,id']]);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('admin/Reports', ['results' => Evaluation::summaries(isset($filters['period']) ? (int) $filters['period'] : null, isset($filters['teacher']) ? (int) $filters['teacher'] : null), 'filters' => $filters, 'periods' => EvaluationPeriod::orderByDesc('id')->get(['id', 'name', 'school_year']), 'teachers' => User::whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher'))->orderBy('name')->get(['id', 'name'])]);
    }

    public function download(Request $request): StreamedResponse
    {
        Gate::authorize('reports.generate');
        Gate::authorize('reports.download');
        $filters = $this->filters($request);
        $results = Evaluation::summaries(isset($filters['period']) ? (int) $filters['period'] : null, isset($filters['teacher']) ? (int) $filters['teacher'] : null);

        return response()->streamDownload(function () use ($results): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            } fputcsv($file, ['Teacher', 'Evaluation period', 'School year', 'Status', 'Responses', 'Score', 'Maximum', 'Performance level', 'Template', 'Template version'], ',', '"', '');
            foreach ($results as $row) {
                $values = [$row['teacher'], $row['period'], $row['school_year'], $row['status'], $row['count'], number_format($row['score'], 2), $row['scale_max'], $row['performance_level'] ?? 'Not configured', $row['template_name'], $row['template_version']];
                $values = array_map(fn ($value): string => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".(string) $value : (string) $value, $values);
                fputcsv($file, $values, ',', '"', '');
            } fclose($file);
        }, 'edumetric-results-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
