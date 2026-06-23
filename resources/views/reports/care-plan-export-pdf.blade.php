<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Care Plan Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        h2 { margin: 18px 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; page-break-after: avoid; }
        h3 { margin: 12px 0 4px; font-size: 11px; color: #334155; page-break-after: avoid; }
        .logo-wrap { text-align: center; margin-bottom: 10px; }
        .logo-fallback { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 14px; color: #475569; text-align: center; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background: #d1fae5; color: #047857; }
        .badge-draft { background: #e2e8f0; color: #475569; }
        .badge-review { background: #e0f2fe; color: #0369a1; }
        .field { margin-bottom: 8px; page-break-inside: avoid; }
        .label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 1px; }
        .value { white-space: pre-wrap; font-size: 10px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { padding: 3px 8px 3px 0; vertical-align: top; width: 50%; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 9px; }
        .summary-table th, .summary-table td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        .summary-table th { background: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .version-table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 9px; }
        .version-table th, .version-table td { border: 1px solid #e2e8f0; padding: 4px 5px; text-align: left; vertical-align: top; }
        .version-table th { background: #f8fafc; font-size: 8px; text-transform: uppercase; }
        .section-block { margin-bottom: 20px; page-break-inside: avoid; }
        .section-block + .section-block { page-break-before: auto; }
        .purpose { font-size: 9px; color: #64748b; margin-bottom: 8px; font-style: italic; }
        .footer-meta { margin-top: 16px; font-size: 8px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .confidential { text-align: center; font-size: 9px; font-weight: bold; color: #be123c; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .export-ref { font-family: monospace; font-size: 9px; color: #475569; }
        .page-break { page-break-before: always; }
        .empty-note { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/allocare-logo.png');
        $logoData = null;
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }
        $statusBadge = fn (?string $status) => match ($status) {
            'Active' => 'badge-active',
            'Under Review' => 'badge-review',
            default => 'badge-draft',
        };
    @endphp

    @if($logoData)
        <div class="logo-wrap">
            <img src="{{ $logoData }}" alt="AlloCare logo" style="height: 56px;" />
        </div>
    @else
        <div class="logo-fallback">AlloCare</div>
    @endif

    <div class="confidential">Confidential — For authorised care coordination and regulatory purposes only</div>

    <h1>Care Plan Export</h1>
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

    <h2>Export Summary</h2>
    <table class="grid">
        <tr>
            <td>
                <div class="label">Scope</div>
                <div class="value">{{ $scopeLabel }}</div>
            </td>
            <td>
                <div class="label">Active sections included</div>
                <div class="value">{{ count($sections) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Supporting documents listed</div>
                <div class="value">{{ count($externalDocuments) }}</div>
            </td>
            <td>
                <div class="label">Export format</div>
                <div class="value">{{ strtoupper($format) }}</div>
            </td>
        </tr>
    </table>

    @if(count($sections) > 0)
        <h3>Section overview</h3>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Last updated</th>
                    <th>Review due</th>
                    <th>Version</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sections as $section)
                    <tr>
                        <td>{{ $section['title'] }}</td>
                        <td><span class="badge {{ $statusBadge($section['status']) }}">{{ $section['status'] }}</span></td>
                        <td>{{ $section['author'] ?? '—' }}</td>
                        <td>{{ $section['lastUpdatedAtLabel'] ?? '—' }}</td>
                        <td>{{ $section['reviewDueAtLabel'] ?? '—' }}</td>
                        <td>{{ $section['currentVersionNumber'] ? 'v'.$section['currentVersionNumber'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach($sections as $index => $section)
        <div class="{{ $index > 0 ? 'page-break' : '' }} section-block">
            <h2>{{ $section['title'] }}</h2>
            @if(!empty($section['purpose']))
                <p class="purpose">{{ $section['purpose'] }}</p>
            @endif

            <table class="grid">
                <tr>
                    <td>
                        <div class="label">Status</div>
                        <span class="badge {{ $statusBadge($section['status']) }}">{{ $section['status'] }}</span>
                    </td>
                    <td>
                        <div class="label">Review due</div>
                        <div class="value">{{ $section['reviewDueAtLabel'] ?? 'Not recorded' }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Last updated</div>
                        <div class="value">{{ $section['lastUpdatedAtLabel'] ?? 'Not yet updated' }}</div>
                    </td>
                    <td>
                        <div class="label">Author</div>
                        <div class="value">{{ $section['author'] ?? 'Not recorded' }}</div>
                    </td>
                </tr>
            </table>

            @if(!empty($section['fields']))
                <h3>Care plan content</h3>
                @foreach($section['fields'] as $field)
                    <div class="field">
                        <div class="label">{{ $field['label'] }}</div>
                        <div class="value">{{ $field['value'] }}</div>
                    </div>
                @endforeach
            @else
                <p class="empty-note">No recorded content for this section.</p>
            @endif

            @if(!empty($section['versions']))
                <h3>Version history</h3>
                <table class="version-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Recorded</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Review due</th>
                            <th>Change summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['versions'] as $version)
                            <tr>
                                <td>v{{ $version['versionNumber'] }}</td>
                                <td>{{ $version['recordedAtLabel'] ?? '—' }}</td>
                                <td>{{ $version['authorName'] ?? '—' }}</td>
                                <td>{{ $version['status'] ?? '—' }}</td>
                                <td>{{ $version['reviewDueAtLabel'] ?? '—' }}</td>
                                <td>{{ $version['changeSummary'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    @if(count($externalDocuments) > 0)
        <div class="page-break section-block">
            <h2>Supporting documentation</h2>
            <p class="purpose">External care plans and documents uploaded to this service user record.</p>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Source</th>
                        <th>Issued</th>
                        <th>Uploaded by</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($externalDocuments as $document)
                        <tr>
                            <td>{{ $document['title'] }}</td>
                            <td>{{ $document['sourceLabel'] }}</td>
                            <td>{{ $document['issuedAt'] ?? '—' }}</td>
                            <td>{{ $document['uploadedBy'] ?? '—' }}</td>
                            <td>{{ $document['fileName'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($format === 'zip')
                <p class="purpose">Attached files are included in the ZIP export package under supporting-documents/.</p>
            @endif
        </div>
    @endif

    <div class="footer-meta">
        This export was generated from AlloCare on {{ $generatedAtLabel }} by {{ $generatedBy }}.
        Export reference {{ $exportReference }} — retain for audit and information governance records.
    </div>
</body>
</html>
