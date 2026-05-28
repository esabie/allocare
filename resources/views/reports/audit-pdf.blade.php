<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Allocare Audit Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { margin: 0 0 8px; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        th { background: #f1f5f9; text-align: left; }
    </style>
</head>
<body>
    <h1>Allocare Audit Report</h1>
    <div class="meta">Generated {{ now()->format('d M Y H:i') }} | Area: {{ $subjectType ?? 'all' }}</div>
    <table>
        <thead>
            <tr>
                <th>When</th><th>User</th><th>Subject</th><th>Description</th><th>Action</th><th>Path</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($events ?? []) as $event)
                <tr>
                    <td>{{ $event['created_at'] ?? '-' }}</td>
                    <td>{{ $event['user_name'] ?? (($event['user_id'] ?? null) ? 'User #'.$event['user_id'] : 'System') }}</td>
                    <td>{{ $event['subject_label'] ?? $event['subject_key'] ?? '-' }}</td>
                    <td>{{ $event['description'] ?? '-' }}</td>
                    <td>{{ $event['action'] ?? '-' }}</td>
                    <td>{{ $event['request_path'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No audit events for this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
