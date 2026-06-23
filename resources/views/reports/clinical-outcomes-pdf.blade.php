<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AlloCare Clinical Outcomes</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { margin: 0 0 8px; font-size: 18px; text-align: center; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; }
        th { background: #f1f5f9; text-align: left; }
    </style>
</head>
<body>
    <h1>Clinical Outcomes Report</h1>
    <div class="meta">
        {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}
        | Generated {{ now()->format('d M Y, H:i') }} by {{ $generatedBy ?? 'System' }}
    </div>

    <table>
        <tbody>
            <tr><th>Vital entries</th><td>{{ $stats['vitalEntries'] ?? 0 }}</td></tr>
            <tr><th>Fluid entries</th><td>{{ $stats['fluidEntries'] ?? 0 }}</td></tr>
            <tr><th>Bowel entries</th><td>{{ $stats['bowelEntries'] ?? 0 }}</td></tr>
            <tr><th>Wound assessments</th><td>{{ $stats['woundAssessments'] ?? 0 }}</td></tr>
            <tr><th>High pain flags</th><td>{{ $stats['highPainFlags'] ?? 0 }}</td></tr>
            <tr><th>Low SpO₂ flags</th><td>{{ $stats['lowSpo2Flags'] ?? 0 }}</td></tr>
            <tr><th>Wound escalations</th><td>{{ $stats['woundEscalations'] ?? 0 }}</td></tr>
            <tr><th>Fluid intake (ml)</th><td>{{ $stats['fluidIntakeMl'] ?? 0 }}</td></tr>
        </tbody>
    </table>

    <h2>Weekly vital trends</h2>
    <table>
        <thead>
            <tr><th>Week</th><th>Entries</th><th>Avg heart rate</th><th>Avg SpO₂</th></tr>
        </thead>
        <tbody>
            @forelse(($weeklyVitals ?? []) as $row)
                <tr>
                    <td>{{ $row['week'] ?? '-' }}</td>
                    <td>{{ $row['count'] ?? 0 }}</td>
                    <td>{{ $row['avgHeartRate'] ?? '-' }}</td>
                    <td>{{ $row['avgSpo2'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No vital data in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
