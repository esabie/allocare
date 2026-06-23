<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Care Notes Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        h2 { margin: 18px 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; page-break-after: avoid; }
        .logo-wrap { text-align: center; margin-bottom: 10px; }
        .logo-fallback { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; font-size: 10px; }
        .confidential { text-align: center; font-size: 9px; font-weight: bold; color: #be123c; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .export-ref { font-family: monospace; font-size: 9px; color: #475569; }
        .label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 1px; }
        .value { white-space: pre-wrap; font-size: 10px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { padding: 3px 8px 3px 0; vertical-align: top; width: 50%; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 9px; }
        .summary-table th, .summary-table td { border: 1px solid #cbd5e1; padding: 6px 7px; text-align: left; vertical-align: top; }
        .summary-table th { background: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .summary-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .col-num { width: 4%; text-align: center; color: #64748b; font-weight: bold; }
        .col-when { width: 14%; }
        .col-author { width: 14%; }
        .col-note { width: 54%; }
        .col-status { width: 10%; text-align: center; }
        .when-date { font-weight: bold; color: #0f172a; display: block; }
        .when-time { color: #64748b; font-size: 8px; }
        .author-name { font-weight: bold; color: #0f172a; }
        .note-text { white-space: pre-wrap; line-height: 1.5; color: #1e293b; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .badge-amended { background: #fef3c7; color: #b45309; }
        .badge-current { background: #d1fae5; color: #047857; }
        .amended-detail { margin-top: 5px; padding-top: 4px; border-top: 1px dashed #e2e8f0; font-size: 8px; color: #64748b; font-style: italic; }
        .empty-note { color: #94a3b8; font-style: italic; text-align: center; padding: 16px; }
        .footer-meta { position: fixed; right: 18px; bottom: 12px; font-size: 10px; color: #475569; text-align: right; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/login-logo.png');
        $logoData = null;
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        $patientName = $patient['name'] ?? ($patient->name ?? 'Patient');
        $patientReference = $patient['reference'] ?? ($patient->reference ?? null);
        $patientNhs = $patient['nhs_number'] ?? ($patient->nhs_number ?? null);
        $patientDob = $patient['dob'] ?? (
            isset($patient->dob) && $patient->dob
                ? \Carbon\Carbon::parse($patient->dob)->format('d M Y')
                : null
        );

        $entriesList = collect($entries ?? []);
        $totalNotes = $summary['total'] ?? $entriesList->count();

        $splitDateTime = static function (?string $label): array {
            if ($label === null || trim($label) === '') {
                return ['—', ''];
            }

            $parts = preg_split('/,\s*/', trim($label), 2);

            return [
                $parts[0] ?? $label,
                $parts[1] ?? '',
            ];
        };
    @endphp

    @if($logoData)
        <div class="logo-wrap">
            <img src="{{ $logoData }}" alt="Allocare logo" style="height: 110px;" />
        </div>
    @else
        <div class="logo-fallback">Allocare</div>
    @endif


    <h1>Care Notes</h1>

    <h2>Export summary</h2>
    <table class="grid">
        <tr>
            <td>
                <div class="label">Patient</div>
                <div class="value"><strong>{{ $patientName }}</strong></div>
            </td>
            <td>
                <div class="label">Total notes</div>
                <div class="value">{{ $totalNotes }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Reference</div>
                <div class="value">{{ $patientReference ?: '—' }}</div>
            </td>
            <td>
                <div class="label">NHS number</div>
                <div class="value">{{ $patientNhs ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Date of birth</div>
                <div class="value">{{ $patientDob ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Date range</div>
                <div class="value">{{ $summary['periodLabel'] ?? '—' }}</div>
            </td>
        </tr>
        @if(!empty($search))
            <tr>
                <td colspan="2">
                    <div class="label">Search filter applied</div>
                    <div class="value">"{{ $search }}"</div>
                </td>
            </tr>
        @endif
    </table>

    <h2>Care notes record</h2>
    <table class="summary-table">
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th class="col-when">Date &amp; time</th>
                <th class="col-author">Recorded by</th>
                <th class="col-note">Care note</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entriesList as $index => $entry)
                @php [$datePart, $timePart] = $splitDateTime($entry['recordedAtLabel'] ?? null); @endphp
                <tr>
                    <td class="col-num">{{ $index + 1 }}</td>
                    <td class="col-when">
                        <span class="when-date">{{ $datePart }}</span>
                        @if($timePart !== '')
                            <span class="when-time">{{ $timePart }}</span>
                        @endif
                    </td>
                    <td class="col-author">
                        <span class="author-name">{{ $entry['author']['name'] ?? 'Unknown' }}</span>
                    </td>
                    <td class="col-note">
                        <div class="note-text">{{ trim((string) ($entry['body'] ?? '')) !== '' ? $entry['body'] : 'No note text recorded.' }}</div>
                        @if(!empty($entry['wasAmended']))
                            <div class="amended-detail">
                                Amended {{ $entry['amendedAtLabel'] ?? '' }} by {{ $entry['amendedBy']['name'] ?? 'Unknown' }}.
                            </div>
                        @endif
                    </td>
                    <td class="col-status">
                        @if(!empty($entry['wasAmended']))
                            <span class="badge badge-amended">Amended</span>
                        @else
                            <span class="badge badge-current">Current</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-note">No care notes recorded for this export.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-meta">
        Generated by: {{ $generatedBy }}<br>
        {{ $generatedAtLabel }}
    </div>
</body>
</html>
