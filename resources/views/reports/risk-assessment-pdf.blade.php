<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Risk Assessment</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        h2 { margin: 16px 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; page-break-after: avoid; }
        .logo-wrap { text-align: center; margin-bottom: 10px; }
        .logo-fallback { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; font-size: 10px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-red { background: #ffe4e6; color: #be123c; }
        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-green { background: #d1fae5; color: #047857; }
        .field { margin-bottom: 10px; page-break-inside: avoid; }
        .label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
        .value { white-space: pre-wrap; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { padding: 4px 8px 4px 0; vertical-align: top; width: 50%; }
        .footer-meta { margin-top: 20px; font-size: 9px; color: #64748b; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .suggested { font-size: 9px; color: #64748b; margin-top: 4px; }
        .confidential { text-align: center; font-size: 9px; font-weight: bold; color: #be123c; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .export-ref { font-family: monospace; font-size: 9px; color: #475569; }
        .link-list { margin: 0; padding-left: 16px; }
        .version-table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 9px; }
        .version-table th, .version-table td { border: 1px solid #e2e8f0; padding: 4px 5px; text-align: left; vertical-align: top; }
        .version-table th { background: #f8fafc; font-size: 8px; text-transform: uppercase; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/allocare-logo.png');
        $logoData = null;
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }
        $level = $assessment['risk_level'] ?? 'amber';
        $badgeClass = match ($level) {
            'red', 'high' => 'badge-red',
            'green', 'low' => 'badge-green',
            default => 'badge-amber',
        };
    @endphp
    @if($logoData)
        <div class="logo-wrap">
            <img src="{{ $logoData }}" alt="AlloCare logo" style="height: 64px;" />
        </div>
    @else
        <div class="logo-fallback">AlloCare</div>
    @endif

    <div class="confidential">Confidential — For commissioner, CQC, and authorised care coordination purposes</div>

    <h1>{{ $title }}</h1>
    <div class="meta">
        <strong>{{ $patient->name }}</strong>
        @if($patient->nhs_number) — NHS {{ $patient->nhs_number }} @endif
        @if($patient->reference) — {{ $patient->reference }} @endif
        <br>
        Generated {{ $generatedAtLabel ?? now()->format('d M Y H:i') }} by {{ $generatedBy }}
        <br>
        @if(!empty($exportReference))
            <span class="export-ref">Export reference: {{ $exportReference }}</span>
        @endif
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">RAG rating</div>
                <span class="badge {{ $badgeClass }}">{{ $assessment['risk_level_label'] ?? '—' }}</span>
            </td>
            <td>
                <div class="label">Status</div>
                <div class="value">{{ $assessment['status_label'] ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Responsible owner</div>
                <div class="value">{{ $assessment['owner_name'] ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Last reviewed</div>
                <div class="value">{{ $assessment['last_reviewed_at_label'] ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Next review due</div>
                <div class="value">{{ $assessment['next_review_due_at_label'] ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Review cycle</div>
                <div class="value">{{ ($assessment['review_cycle_months'] ?? '—') }} months</div>
            </td>
        </tr>
    </table>

    @if(!empty($assessment['linked_care_plans']) || !empty($assessment['linked_incidents']))
        <h2>Linked records</h2>
        @if(!empty($assessment['linked_care_plans']))
            <div class="field">
                <div class="label">Care plan sections</div>
                <ul class="link-list">
                    @foreach($assessment['linked_care_plans'] as $plan)
                        <li>{{ $plan['title'] ?? $plan['slug'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(!empty($assessment['linked_incidents']))
            <div class="field">
                <div class="label">Related incidents</div>
                <ul class="link-list">
                    @foreach($assessment['linked_incidents'] as $incident)
                        <li>{{ $incident['title'] ?? 'Incident' }}@if(!empty($incident['dateLabel'])) — {{ $incident['dateLabel'] }}@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    <h2>Risk statement</h2>
    <div class="field value">{{ $assessment['risk_statement'] ?? 'Not recorded' }}</div>

    <h2>Triggers</h2>
    <div class="field value">{{ $assessment['triggers'] ?? 'Not recorded' }}</div>

    <h2>Proactive controls</h2>
    <div class="field value">{{ $assessment['proactive_controls'] ?? 'Not recorded' }}</div>

    <h2>Active controls</h2>
    <div class="field value">{{ $assessment['active_controls'] ?? 'Not recorded' }}</div>
    @if(!empty($suggestedControls))
        <p class="suggested">Template suggestions: {{ implode(' · ', $suggestedControls) }}</p>
    @endif

    <h2>Reactive controls</h2>
    <div class="field value">{{ $assessment['reactive_controls'] ?? 'Not recorded' }}</div>

    <h2>Monitoring requirements</h2>
    <div class="field value">{{ $assessment['monitoring_requirements'] ?? 'Not recorded' }}</div>

    <h2>Escalation pathway</h2>
    <div class="field value">{{ $assessment['escalation_pathway'] ?? 'Not recorded' }}</div>

    <h2>Capacity and consent notes</h2>
    <div class="field value">{{ $assessment['capacity_consent_notes'] ?? 'Not recorded' }}</div>

    <h2>Legal restrictions</h2>
    <div class="field value">{{ $assessment['legal_restrictions'] ?? 'Not recorded' }}</div>

    @if(!empty($assessment['version_history']))
        <h2>Version history (permanent audit trail)</h2>
        <table class="version-table">
            <thead>
                <tr>
                    <th>Recorded</th>
                    <th>Author</th>
                    <th>Change summary</th>
                    <th>RAG</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assessment['version_history'] as $version)
                    <tr>
                        <td>{{ $version['recordedAtLabel'] ?? '—' }}</td>
                        <td>{{ $version['authorName'] ?? '—' }}</td>
                        <td>{{ $version['changeSummary'] ?? '—' }}</td>
                        <td>{{ $version['riskLevelLabel'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-meta">
        Recorded by: {{ $assessment['author_name'] ?? 'Unknown' }}
        @if(!empty($assessment['updated_at_label'])) — last saved {{ $assessment['updated_at_label'] }} @endif
        <br>
        Generated by: {{ $generatedBy }} — AlloCare
    </div>
</body>
</html>
