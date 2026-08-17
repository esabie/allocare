<?php

namespace App\Support;

use App\Models\CareJournalEntry;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DailySupportCareLog
{
    public static function templateSlug(): string
    {
        return (string) config('daily_support_care_log.template_slug', 'daily_support_care_log');
    }

    public static function label(): string
    {
        return (string) config('daily_support_care_log.label', 'Daily Support Care Log');
    }

    /** @return array<int, array{key: string, label: string}> */
    public static function slotsForShift(string $shiftType): array
    {
        $key = $shiftType === 'night' ? 'night_slots' : 'day_slots';

        return config("daily_support_care_log.{$key}", []);
    }

    /** @return array<string, mixed> */
    public static function validatePayload(array $payload): array
    {
        $validated = validator($payload, [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'shift_type' => ['required', 'string', 'in:day,night'],
            'log_date' => ['required', 'date'],
            'structured_data' => ['required', 'array'],
            'structured_data.slots' => ['array'],
            'structured_data.incident' => ['required', 'string', 'in:yes,no'],
            'structured_data.shift_comments' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $shiftType = $validated['shift_type'];
        $structured = $validated['structured_data'];
        $allowedKeys = collect(self::slotsForShift($shiftType))->pluck('key')->all();
        $normalizedSlots = [];

        foreach ($allowedKeys as $slotKey) {
            $row = is_array($structured['slots'][$slotKey] ?? null) ? $structured['slots'][$slotKey] : [];
            $initials = trim((string) ($row['initials'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));

            if ($initials === '' && $notes === '') {
                continue;
            }

            if ($notes === '') {
                throw ValidationException::withMessages([
                    'structured_data' => "Add notes for the {$slotKey} time slot or leave the row blank.",
                ]);
            }

            $normalizedSlots[$slotKey] = [
                'initials' => $initials !== '' ? $initials : null,
                'notes' => $notes,
            ];
        }

        if ($normalizedSlots === []) {
            throw ValidationException::withMessages([
                'structured_data' => 'Complete at least one time slot with notes before saving.',
            ]);
        }

        $occupied = self::occupiedSlots(
            (int) $validated['patient_id'],
            $validated['log_date'],
            $shiftType,
        );

        foreach (array_keys($normalizedSlots) as $slotKey) {
            if (! isset($occupied[$slotKey])) {
                continue;
            }

            $label = $occupied[$slotKey]['label'] ?? $slotKey;
            throw ValidationException::withMessages([
                'structured_data' => "The {$label} time slot has already been recorded for this date.",
            ]);
        }

        $normalized = [
            'log_date' => $validated['log_date'],
            'shift_type' => $shiftType,
            'slots' => $normalizedSlots,
            'incident' => $structured['incident'],
            'shift_comments' => trim((string) ($structured['shift_comments'] ?? '')) ?: null,
        ];

        return [
            'patient_id' => (int) $validated['patient_id'],
            'shift_type' => $shiftType,
            'template_slug' => self::templateSlug(),
            'structured_data' => $normalized,
            'body' => self::buildBody($normalized),
        ];
    }

    /** @param  array<string, mixed>  $structured */
    public static function buildBody(array $structured): string
    {
        $shiftType = (string) ($structured['shift_type'] ?? 'day');
        $shiftLabel = $shiftType === 'night' ? 'Night' : 'Day';
        $lines = [
            '['.self::label().']',
            'Date: '.($structured['log_date'] ?? '—'),
            'Shift: '.$shiftLabel,
        ];

        foreach (self::slotsForShift($shiftType) as $slot) {
            $key = $slot['key'];
            $row = $structured['slots'][$key] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $initials = trim((string) ($row['initials'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));
            if ($notes === '') {
                continue;
            }

            $prefix = $slot['label'];
            if ($initials !== '') {
                $prefix .= " ({$initials})";
            }

            $lines[] = $prefix.': '.$notes;
        }

        $incident = ($structured['incident'] ?? 'no') === 'yes' ? 'Yes' : 'No';
        $lines[] = 'Incident: '.$incident;

        if (! empty($structured['shift_comments'])) {
            $lines[] = 'Shift comments: '.$structured['shift_comments'];
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $structured
     * @return array<int, array{label: string, value: string}>
     */
    public static function structuredSummary(array $structured): array
    {
        $shiftType = (string) ($structured['shift_type'] ?? 'day');
        $rows = [
            ['label' => 'Date', 'value' => (string) ($structured['log_date'] ?? '—')],
            ['label' => 'Shift', 'value' => $shiftType === 'night' ? 'Night' : 'Day'],
        ];

        foreach (self::slotsForShift($shiftType) as $slot) {
            $row = $structured['slots'][$slot['key']] ?? null;
            if (! is_array($row) || trim((string) ($row['notes'] ?? '')) === '') {
                continue;
            }

            $value = (string) $row['notes'];
            if (! empty($row['initials'])) {
                $value = '['.$row['initials'].'] '.$value;
            }

            $rows[] = [
                'label' => $slot['label'],
                'value' => $value,
            ];
        }

        $rows[] = [
            'label' => 'Incident',
            'value' => ($structured['incident'] ?? 'no') === 'yes' ? 'Yes' : 'No',
        ];

        if (! empty($structured['shift_comments'])) {
            $rows[] = [
                'label' => 'Shift comments',
                'value' => (string) $structured['shift_comments'],
            ];
        }

        return $rows;
    }

    /** @return array<string, array{label: string, initials: ?string, notes: string, recordedAtLabel: ?string, authorName: string}> */
    public static function occupiedSlots(int $patientId, string $logDate, string $shiftType): array
    {
        $entries = CareJournalEntry::query()
            ->with('author:id,name,first_name,surname')
            ->where('patient_id', $patientId)
            ->where('template_slug', self::templateSlug())
            ->where('shift_type', $shiftType)
            ->get();

        $slotLabels = collect(self::slotsForShift($shiftType))->pluck('label', 'key');
        $occupied = [];

        foreach ($entries as $entry) {
            $structured = is_array($entry->structured_data) ? $entry->structured_data : [];
            if (($structured['log_date'] ?? '') !== $logDate) {
                continue;
            }

            $slots = is_array($structured['slots'] ?? null) ? $structured['slots'] : [];
            foreach ($slots as $slotKey => $row) {
                if (! is_array($row) || trim((string) ($row['notes'] ?? '')) === '') {
                    continue;
                }

                if (isset($occupied[$slotKey])) {
                    continue;
                }

                $occupied[$slotKey] = [
                    'label' => (string) ($slotLabels[$slotKey] ?? $slotKey),
                    'initials' => ! empty($row['initials']) ? (string) $row['initials'] : null,
                    'notes' => (string) $row['notes'],
                    'recordedAtLabel' => $entry->recorded_at?->format('d M Y, H:i:s'),
                    'authorName' => format_care_journal_author_name($entry->author),
                ];
            }
        }

        return $occupied;
    }

    /** @return array{day_slots: array<int, array{key: string, label: string}>, night_slots: array<int, array{key: string, label: string}>} */
    public static function frontendConfig(): array
    {
        return [
            'templateSlug' => self::templateSlug(),
            'label' => self::label(),
            'daySlots' => config('daily_support_care_log.day_slots', []),
            'nightSlots' => config('daily_support_care_log.night_slots', []),
        ];
    }
}
