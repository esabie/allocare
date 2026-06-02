<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Access Request Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; line-height: 1.35; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 11px; margin: 14px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
        .meta { text-align: center; color: #475569; margin-bottom: 12px; font-size: 9px; }
        .logo-wrap { text-align: center; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; text-align: left; }
        th { background: #f1f5f9; font-size: 8px; }
        .kv td:first-child { width: 32%; font-weight: 600; background: #f8fafc; }
        .muted { color: #64748b; font-size: 8px; }
        .footer { margin-top: 16px; font-size: 8px; color: #64748b; text-align: right; }
        .empty { font-style: italic; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/allocare-logo.png');
        $logoData = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    @if($logoData)
        <div class="logo-wrap"><img src="{{ $logoData }}" alt="AlloCare" style="height: 56px;" /></div>
    @endif
    <h1>Subject Access Request — Data Export</h1>
    <div class="meta">
        <strong>{{ $patientName }}</strong>
        @if($patientReference) — Ref {{ $patientReference }} @endif
        @if($nhsNumber) — NHS {{ $nhsNumber }} @endif<br>
        Exported {{ $exportedAt }} · Request #{{ $requestId }}
    </div>

    <h2>Profile summary</h2>
    <table class="kv">
        @foreach($profileRows as $label => $value)
            <tr>
                <td>{{ $label }}</td>
                <td>{{ $value !== '' && $value !== null ? $value : '—' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Observations (latest {{ count($observations) }})</h2>
    @if(count($observations) === 0)
        <p class="empty">No observations recorded.</p>
    @else
        <table>
            <thead><tr><th>When</th><th>HR</th><th>BP</th><th>SpO₂</th><th>Notes</th></tr></thead>
            <tbody>
                @foreach($observations as $row)
                    <tr>
                        <td>{{ $row['when'] }}</td>
                        <td>{{ $row['hr'] ?? '—' }}</td>
                        <td>{{ $row['bp'] ?? '—' }}</td>
                        <td>{{ $row['spo2'] ?? '—' }}</td>
                        <td>{{ $row['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Fluid balance (latest {{ count($fluidRecords) }})</h2>
    @if(count($fluidRecords) === 0)
        <p class="empty">No fluid records.</p>
    @else
        <table>
            <thead><tr><th>When</th><th>Intake ml</th><th>Output ml</th><th>Notes</th></tr></thead>
            <tbody>
                @foreach($fluidRecords as $row)
                    <tr>
                        <td>{{ $row['when'] }}</td>
                        <td>{{ $row['intake'] ?? '—' }}</td>
                        <td>{{ $row['output'] ?? '—' }}</td>
                        <td>{{ $row['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Bowel chart (latest {{ count($bowelRecords) }})</h2>
    @if(count($bowelRecords) === 0)
        <p class="empty">No bowel entries.</p>
    @else
        <table>
            <thead><tr><th>When</th><th>Entry</th><th>Notes</th></tr></thead>
            <tbody>
                @foreach($bowelRecords as $row)
                    <tr>
                        <td>{{ $row['when'] }}</td>
                        <td>{{ $row['summary'] }}</td>
                        <td>{{ $row['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Medications</h2>
    @if(count($medications) === 0)
        <p class="empty">No medications on file.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Dose</th><th>Route</th><th>Active</th></tr></thead>
            <tbody>
                @foreach($medications as $med)
                    <tr>
                        <td>{{ $med['name'] }}</td>
                        <td>{{ $med['dose'] ?? '—' }}</td>
                        <td>{{ $med['route'] ?? '—' }}</td>
                        <td>{{ $med['active'] ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Care journal (latest {{ count($journal) }})</h2>
    @if(count($journal) === 0)
        <p class="empty">No journal entries.</p>
    @else
        @foreach($journal as $entry)
            <p><strong>{{ $entry['when'] }}</strong> — {{ $entry['author'] }}<br>{{ $entry['body'] }}</p>
        @endforeach
    @endif

    <p class="footer">This export is generated from Allocare for GDPR subject access disclosure. Verify identity and redact third-party data before release.</p>
</body>
</html>
