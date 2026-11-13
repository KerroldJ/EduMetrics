<?php

namespace App\Http\Controllers\Admin;

use App\Actions\GenerateReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response as PdfResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** @return array{period: int|null} */
    private function filters(Request $request, bool $requirePeriod = false): array
    {
        $filters = $request->validate(['period' => [$requirePeriod ? 'required' : 'nullable', 'integer', 'exists:evaluation_periods,id']]);

        return ['period' => isset($filters['period']) ? (int) $filters['period'] : null];
    }

    public function index(Request $request): Response
    {
        return Inertia::render('admin/Reports', [
            ...$this->reportData($request),
            'periods' => EvaluationPeriod::with('form:id,name,version,scale_min,scale_max')->withCount('evaluations')
                ->orderByDesc('school_year')->orderByDesc('opens_at')->orderByDesc('id')
                ->get(['id', 'name', 'school_year', 'status', 'opens_at', 'closes_at', 'evaluation_form_id']),
        ]);
    }

    public function pdf(Request $request, GenerateReportPdf $generator): PdfResponse
    {
        Gate::authorize('results.view-all');
        Gate::authorize('reports.generate');
        Gate::authorize('reports.download');
        $data = $this->reportData($request, requirePeriod: true);

        return response($generator->handle(view('reports.evaluations', $data)->render()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="edumetric-period-'.$data['filters']['period'].'-results-'.now()->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** @return array<string, mixed> */
    private function reportData(Request $request, bool $requirePeriod = false): array
    {
        $filters = $this->filters($request, $requirePeriod);
        $period = $filters['period'] ? EvaluationPeriod::with('form:id,name,version,scale_min,scale_max')->withCount('evaluations')->findOrFail($filters['period']) : null;

        return [
            'results' => $period ? Evaluation::summaries(periodId: $period->id, includeDetails: true) : [],
            'selectedPeriod' => $period?->toArray(),
            'filters' => $filters,
            'generatedAt' => now()->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'periodLabel' => $period ? $period->name.' · '.$period->school_year : 'Evaluation periods',
        ];
    }

    public function download(Request $request): StreamedResponse
    {
        Gate::authorize('reports.generate');
        Gate::authorize('reports.download');
        $filters = $this->filters($request, requirePeriod: true);
        $results = Evaluation::summaries(periodId: $filters['period']);

        return response()->streamDownload(function () use ($results): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            } fputcsv($file, ['Teacher', 'Evaluation period', 'School year', 'Status', 'Responses', 'Score', 'Maximum', 'Template', 'Template version'], ',', '"', '');
            foreach ($results as $row) {
                $values = [$row['teacher'], $row['period'], $row['school_year'], $row['status'], $row['count'], number_format($row['score'], 2), $row['scale_max'], $row['template_name'], $row['template_version']];
                $values = array_map(fn ($value): string => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".(string) $value : (string) $value, $values);
                fputcsv($file, $values, ',', '"', '');
            } fclose($file);
        }, 'edumetric-period-'.$filters['period'].'-results-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
