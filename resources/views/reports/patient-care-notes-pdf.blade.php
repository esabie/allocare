<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AlloCare Care Notes</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { margin: 0 0 8px; font-size: 18px; }
        .meta { margin-bottom: 14px; color: #475569; }
        .note { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 10px; page-break-inside: avoid; }
        .note-meta { font-size: 10px; color: #64748b; margin-bottom: 6px; }
        .note-body { white-space: pre-wrap; line-height: 1.45; }
    </style>
</head>
<body>
    <h1>Care Notes — {{ $patient->name ?? 'Service user' }}</h1>
    <div class="meta">
        Generated {{ now()->format('d M Y, H:i') }} by {{ $generatedBy ?? 'System' }}
        @if(!empty($search))
            | Filter: "{{ $search }}"
        @endif
        | {{ count($entries ?? []) }} note(s)
    </div>

    @forelse(($entries ?? []) as $entry)
        <div class="note">
            <div class="note-meta">
                <strong>{{ $entry['recordedAtLabel'] ?? '-' }}</strong>
                — {{ $entry['author']['name'] ?? 'Unknown author' }}
                @if(!empty($entry['wasAmended']))
                    | Amended {{ $entry['amendedAtLabel'] ?? '' }} by {{ $entry['amendedBy']['name'] ?? 'Unknown' }}
                @endif
            </div>
            <div class="note-body">{{ $entry['body'] ?? '' }}</div>
        </div>
    @empty
        <p>No care notes recorded for this export.</p>
    @endforelse
</body>
</html>
