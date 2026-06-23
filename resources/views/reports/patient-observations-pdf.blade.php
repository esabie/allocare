<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AlloCare Physical Observations</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { margin: 0 0 8px; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; }
        th { background: #f1f5f9; text-align: left; }
        .risk-high { color: #b91c1c; font-weight: bold; }
        .risk-medium { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Physical Observations (NEWS2) — {{ $patient->name ?? 'Service user' }}</h1>
    <div class="meta">Generated {{ now()->format('d M Y, H:i') }} by {{ $generatedBy ?? 'System' }}</div>
    <table>
        <thead>
            <tr>
                <th>Recorded</th>
                <th>NEWS2</th>
                <th>Risk</th>
                <th>RR</th>
                <th>SpO₂</th>
                <th>O₂</th>
                <th>BP</th>
                <th>Pulse</th>
                <th>Temp</th>
                <th>ACVPU</th>
                <th>Escalation</th>
                <th>By</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($observations ?? []) as $row)
                <tr>
                    <td>{{ $row['recordedAtLabel'] ?? '-' }}</td>
                    <td>{{ $row['news2Score'] ?? '-' }}</td>
                    <td class="{{ in_array($row['news2RiskLevel'] ?? '', ['high', 'medium'], true) ? 'risk-'.($row['news2RiskLevel']) : '' }}">{{ $row['news2RiskLabel'] ?? '-' }}</td>
                    <td>{{ $row['respirationRate'] ?? '-' }}</td>
                    <td>{{ $row['spo2'] ?? '-' }}</td>
                    <td>{{ !empty($row['supplementalOxygen']) ? 'Yes' : 'No' }}</td>
                    <td>{{ $row['bpSystolic'] ?? '-' }}/{{ $row['bpDiastolic'] ?? '-' }}</td>
                    <td>{{ $row['heartRate'] ?? '-' }}</td>
                    <td>{{ $row['temperatureCelsius'] ?? '-' }}</td>
                    <td>{{ $row['consciousnessLabel'] ?? '-' }}</td>
                    <td>{{ $row['news2EscalationGuidance'] ?? ($row['otherObservation'] ?? '-') }}</td>
                    <td>{{ $row['recordedBy']['name'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="12">No observations recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
