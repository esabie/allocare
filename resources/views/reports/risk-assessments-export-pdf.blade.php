<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Risk Assessment Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        h2 { margin: 18px 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; page-break-after: avoid; }
        h3 { margin: 12px 0 4px; font-size: 11px; color: #334155; page-break-after: avoid; }
        .logo-wrap { text-align: center; margin-bottom: 10px; }
        .logo-fallback { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-red { background: #ffe4e6; color: #be123c; }
        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-green { background: #d1fae5; color: #047857; }
        .field { margin-bottom: 8px; page-break-inside: avoid; }
        .label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 1px; }
        .value { white-space: pre-wrap; font-size: 10px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { padding: 3px 8px 3px 0; vertical-align: top; width: 50%; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 9px; }
        .summary-table th, .summary-table td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        .summary-table th { background: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .section-block { margin-bottom: 20px; page-break-inside: avoid; }
        .page-break { page-break-before: always; }
        .confidential { text-align: center; font-size: 9px; font-weight: bold; color: #be123c; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .export-ref { font-family: monospace; font-size: 9px; color: #475569; }
        .footer-meta { margin-top: 16px; font-size: 8px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .link-list { margin: 0; padding-left: 16px; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/allocare-logo.png');
        $logoData = null;
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }
    @endphp

    @if($logoData)
        <div class="logo-wrap">
            <img src="{{ $logoData }}" alt="AlloCare logo" style="height: 56px;" />
        </div>
    @else
        <div class="logo-fallback">AlloCare</div>
    @endif

    <div class="confidential">Confidential — For commissioner, CQC, and authorised care coordination purposes only</div>

    <h1>Risk Assessment Export Package</h1>
    <div class="meta">
        <strong>{{ $patient['name'] }}</strong>
        @if(!empty($patient['reference'])) — {{ $patient['reference'] }} @endif
        @if(!empty($patient['nhs_number'])) — NHS {{ $patient['nhs_number'] }} @endif
        @if(!empty($patient['dob'])) — DOB {{ $patient['dob'] }} @endif
        <br>
        Generated {{ $generatedAtLabel }} by {{ $generatedBy }}
        <br>
        <span class="export-ref">Export reference: {{ $exportReference }}</span>
    </div>

    <h2>Export summary</h2>
    <table class="grid">
        <tr>
            <td>
                <div class="label">Recorded assessments</div>
                <div class="value">{{ $recordedCount }}</div>
            </td>
            <td>
                <div class="label">Patient RAG status</div>
                <div class="value">{{ $patient['rag_status_label'] ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Overdue reviews</div>
                <div class="value">{{ $overdueReviewCount }}</div>
            </td>
            <td>
                <div class="label">Purpose</div>
                <div class="value">Commissioner submission, CQC inspection evidence, and clinical handover</div>
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <thead>
            <tr>
                <th>Assessment</th>
                <th>RAG</th>
                <th>Status</th>
                <th>Owner</th>
                <th>Next review</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sections as $section)
                @php
                    $a = $section['assessment'];
                    $level = $a['risk_level'] ?? 'amber';
                    $badgeClass = match ($level) {
                        'red', 'high' => 'badge-red',
                        'green', 'low' => 'badge-green',
                        default => 'badge-amber',
                    };
                @endphp
                <tr>
                    <td>{{ $section['title'] }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $a['risk_level_label'] ?? '—' }}</span></td>
                    <td>{{ $a['status_label'] ?? '—' }}</td>
                    <td>{{ $a['owner_name'] ?? '—' }}</td>
                    <td>{{ $a['next_review_due_at_label'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($sections as $index => $section)
        @php
            $a = $section['assessment'];
            $level = $a['risk_level'] ?? 'amber';
            $badgeClass = match ($level) {
                'red', 'high' => 'badge-red',
                'green', 'low' => 'badge-green',
                default => 'badge-amber',
            };
        @endphp
        <div class="section-block {{ $index > 0 ? 'page-break' : '' }}">
            <h2>{{ $section['title'] }}</h2>
            <table class="grid">
                <tr>
                    <td>
                        <div class="label">RAG rating</div>
                        <span class="badge {{ $badgeClass }}">{{ $a['risk_level_label'] ?? '—' }}</span>
                    </td>
                    <td>
                        <div class="label">Status</div>
                        <div class="value">{{ $a['status_label'] ?? '—' }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Responsible owner</div>
                        <div class="value">{{ $a['owner_name'] ?? '—' }}</div>
                    </td>
                    <td>
                        <div class="label">Next review due</div>
                        <div class="value">{{ $a['next_review_due_at_label'] ?? '—' }}</div>
                    </td>
                </tr>
            </table>

            @if(!empty($a['linked_care_plans']) || !empty($a['linked_incidents']))
                <h3>Linked records</h3>
                @if(!empty($a['linked_care_plans']))
                    <div class="field">
                        <div class="label">Care plan sections</div>
                        <ul class="link-list">
                            @foreach($a['linked_care_plans'] as $plan)
                                <li>{{ $plan['title'] ?? $plan['slug'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if(!empty($a['linked_incidents']))
                    <div class="field">
                        <div class="label">Related incidents</div>
                        <ul class="link-list">
                            @foreach($a['linked_incidents'] as $incident)
                                <li>{{ $incident['title'] ?? 'Incident' }}@if(!empty($incident['dateLabel'])) — {{ $incident['dateLabel'] }}@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            <h3>Risk statement</h3>
            <div class="field value">{{ $a['risk_statement'] ?? 'Not recorded' }}</div>

            <h3>Controls</h3>
            <div class="field"><div class="label">Proactive</div><div class="value">{{ $a['proactive_controls'] ?? 'Not recorded' }}</div></div>
            <div class="field"><div class="label">Active</div><div class="value">{{ $a['active_controls'] ?? 'Not recorded' }}</div></div>
            <div class="field"><div class="label">Reactive</div><div class="value">{{ $a['reactive_controls'] ?? 'Not recorded' }}</div></div>

            <h3>Monitoring & escalation</h3>
            <div class="field value">{{ $a['monitoring_requirements'] ?? 'Not recorded' }}</div>
            <div class="field value">{{ $a['escalation_pathway'] ?? 'Not recorded' }}</div>

            @if(!empty($a['version_history']))
                <h3>Version history</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Recorded</th>
                            <th>Author</th>
                            <th>Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($a['version_history'], 0, 10) as $version)
                            <tr>
                                <td>{{ $version['recordedAtLabel'] ?? '—' }}</td>
                                <td>{{ $version['authorName'] ?? '—' }}</td>
                                <td>{{ $version['changeSummary'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="footer-meta">
        Permanent version history is retained in AlloCare for all recorded assessments.
        <br>
        Export reference {{ $exportReference }} — generated by {{ $generatedBy }}
    </div>
</body>
</html>
