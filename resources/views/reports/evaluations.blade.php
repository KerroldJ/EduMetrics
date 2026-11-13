<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'">
    <title>EduMetric Evaluation Period Report</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font: 11px/1.5 Arial, sans-serif; }
        .brand { color: #185b94; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 5px 0 3px; font-size: 24px; line-height: 1.2; }
        h2 { margin: 22px 0 8px; font-size: 15px; break-after: avoid; }
        h3 { margin: 16px 0 6px; font-size: 12px; break-after: avoid; }
        p { margin: 0; }
        .muted { color: #64748b; }
        .metadata { margin: 18px 0; padding: 12px; border: 1px solid #e2e8f0; border-radius: 5px; }
        .metadata p + p { margin-top: 4px; }
        .note { margin: 12px 0; color: #475569; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #eff6fb; color: #334155; text-align: left; font-size: 10px; }
        th, td { padding: 8px 6px; vertical-align: top; border-bottom: 1px solid #e2e8f0; overflow-wrap: anywhere; }
        tr { break-inside: avoid; }
        .numeric { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .small { font-size: 9px; }
        .status { display: inline-block; font-size: 9px; font-weight: 700; color: #185b94; }
        .feedback { padding: 9px 11px; margin: 7px 0; background: #f8fafc; border-left: 2px solid #cbd5e1; white-space: pre-wrap; overflow-wrap: anywhere; }
        .empty { padding: 24px; text-align: center; border: 1px solid #e2e8f0; border-radius: 5px; }
    </style>
</head>
<body>
    <header>
        <p class="brand">EduMetric</p>
        <h1>Evaluation period report</h1>
        <p class="muted">Generated {{ $generatedAt }} · Philippine time</p>
    </header>
    <div class="metadata">
        <p><strong>Evaluation period:</strong> {{ $periodLabel }}</p>
        <p><strong>Template:</strong> {{ $selectedPeriod['form']['name'] ?? 'Not assigned' }} · Version {{ $selectedPeriod['form']['version'] ?? '—' }}</p>
        <p><strong>Status:</strong> {{ $selectedPeriod['status'] === 'released' ? 'Released' : 'Provisional' }}</p>
        <p><strong>Submitted evaluations:</strong> {{ collect($results)->sum('count') }}</p>
    </div>
    <p class="note">Scores are weighted means rounded to two decimal places. Provisional results are for administrative review. Teachers receive only released results that meet the minimum response threshold. Compare scores using the same template version and rating scale.</p>
    @if (count($results))
        <table>
            <thead>
                <tr>
                    <th style="width:65%">Teacher</th>
                    <th class="numeric" style="width:15%">Responses</th>
                    <th class="numeric" style="width:20%">Average score</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $result)
                    <tr>
                        <td>{{ $result['teacher'] }}</td>
                        <td class="numeric">{{ $result['count'] }}</td>
                        <td class="numeric"><strong>{{ number_format($result['score'], 2) }}</strong> / {{ $result['scale_max'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($filters['period'])
            @foreach ($results as $result)
                <section>
                    <h2>{{ $result['teacher'] }}</h2>
                    @if (count($result['criteria']))
                        <h3>Question averages</h3>
                        <table>
                            <thead><tr><th style="width:85%">Question</th><th class="numeric">Average</th></tr></thead>
                            <tbody>
                                @foreach ($result['criteria'] as $criterion)
                                    <tr><td>{{ $criterion['prompt'] }}</td><td class="numeric">{{ number_format($criterion['average'], 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if (count($result['comments']))
                        <h3>Anonymous student feedback</h3>
                        @foreach ($result['comments'] as $comment)
                            <p class="feedback">{{ $comment }}</p>
                        @endforeach
                    @endif
                </section>
            @endforeach
        @endif
    @else
        <div class="empty"><strong>No submitted evaluations in this period</strong><p class="muted">Reports become available once students submit evaluations.</p></div>
    @endif
</body>
</html>
