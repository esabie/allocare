<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AlloCare Staff Performance</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { margin: 0 0 8px; font-size: 18px; text-align: center; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; }
        th { background: #f1f5f9; text-align: left; }
    </style>
</head>
<body>
    <h1>Staff Performance Report</h1>
    <div class="meta">
        {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}
        | Generated {{ now()->format('d M Y, H:i') }} by {{ $generatedBy ?? 'System' }}
    </div>

    <p style="text-align:center; margin-bottom: 14px;">
        <strong>Total shifts:</strong> {{ $stats['totalShifts'] ?? 0 }}
        &nbsp;|&nbsp;
        <strong>Staff with shifts:</strong> {{ $stats['staffCount'] ?? 0 }}
        &nbsp;|&nbsp;
        <strong>Avg completion:</strong> {{ $stats['avgCompletionRate'] ?? 0 }}%
    </p>

    <table>
        <thead>
            <tr>
                <th>Carer</th>
                <th>Shifts</th>
                <th>Completed</th>
                <th>Missed</th>
                <th>Completion %</th>
                <th>Late minutes</th>
                <th>Hours allocated</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($byStaff ?? []) as $row)
                <tr>
                    <td>{{ $row['staffName'] ?? '-' }}</td>
                    <td>{{ $row['totalShifts'] ?? 0 }}</td>
                    <td>{{ $row['completedShifts'] ?? 0 }}</td>
                    <td>{{ $row['missedShifts'] ?? 0 }}</td>
                    <td>{{ $row['completionRate'] ?? 0 }}%</td>
                    <td>{{ $row['lateMinutesTotal'] ?? 0 }}</td>
                    <td>{{ $row['hoursAllocated'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No shifts in the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
