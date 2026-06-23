<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\PatientCarePlanSummary;
use App\Models\PatientRiskAssessment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CareLogTemplates
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return config('care_log_templates', []);
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $template) {
            if (($template['slug'] ?? '') === $slug) {
                return $template;
            }
        }

        return null;
    }

    public static function label(string $slug): string
    {
        return self::find($slug)['label'] ?? Str::of($slug)->replace('_', ' ')->title()->toString();
    }

    /**
     * @return array{
     *   care_plans: array<int, array<string, string>>,
     *   risk_assessments: array<int, array<string, string>>
     * }
     */
    public static function linkOptionsForPatient(Patient $patient): array
    {
        $carePlans = PatientCarePlanSummary::query()
            ->where('patient_slug', $patient->url_key)
            ->whereIn('status', ['submitted', 'reviewed'])
            ->orderBy('plan_slug')
            ->get()
            ->map(fn (PatientCarePlanSummary $summary) => [
                'slug' => $summary->plan_slug,
                'label' => care_plan_catalogue_by_slug()[$summary->plan_slug]['title']
                    ?? Str::of($summary->plan_slug)->replace('-', ' ')->title()->toString(),
            ])
            ->values()
            ->all();

        $riskAssessments = PatientRiskAssessment::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'active')
            ->orderBy('risk_slug')
            ->get()
            ->map(fn (PatientRiskAssessment $assessment) => [
                'slug' => $assessment->risk_slug,
                'label' => risk_assessment_template($assessment->risk_slug)['title']
                    ?? Str::of($assessment->risk_slug)->replace('-', ' ')->title()->toString(),
                'level' => PatientRiskAssessment::LEVEL_LABELS[PatientRiskAssessment::normalizeLevel($assessment->risk_level)] ?? $assessment->risk_level,
            ])
            ->values()
            ->all();

        return [
            'care_plans' => $carePlans,
            'risk_assessments' => $riskAssessments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validatePayload(array $payload): array
    {
        $validated = validator($payload, [
            'template_slug' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'string', 'max:10000'],
            'structured_data' => ['nullable', 'array'],
            'outcome_status' => ['nullable', 'string', 'in:completed,partial,refused,not_required'],
            'linked_care_plan_slug' => ['nullable', 'string', 'max:128'],
            'linked_support_objective' => ['nullable', 'string', 'max:2000'],
            'linked_risk_assessment_slug' => ['nullable', 'string', 'max:128'],
        ])->validate();

        $templateSlug = trim((string) ($validated['template_slug'] ?? ''));
        $structured = is_array($validated['structured_data'] ?? null) ? $validated['structured_data'] : [];

        if ($templateSlug === '' || $templateSlug === 'general') {
            $body = trim((string) ($validated['body'] ?? ''));
            if ($body === '') {
                throw ValidationException::withMessages([
                    'body' => 'Enter a care note or choose a structured template.',
                ]);
            }
            $validated['body'] = $body;
            $validated['template_slug'] = null;
            $validated['structured_data'] = null;

            return $validated;
        }

        $template = self::find($templateSlug);
        if ($template === null) {
            throw ValidationException::withMessages([
                'template_slug' => 'The selected care log template is not valid.',
            ]);
        }

        $validated['structured_data'] = self::normalizeStructuredData($template, $structured);
        $validated['body'] = self::buildBody(
            $templateSlug,
            $validated['structured_data'],
            $validated['outcome_status'] ?? null,
            $validated['linked_care_plan_slug'] ?? null,
            $validated['linked_support_objective'] ?? null,
            $validated['linked_risk_assessment_slug'] ?? null,
        );

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeStructuredData(array $template, array $data): array
    {
        $normalized = [];

        foreach ($template['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = $data[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            if (($field['type'] ?? '') === 'number' && is_numeric($value)) {
                $normalized[$key] = str_contains((string) $value, '.') ? (float) $value : (int) $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'structured_data' => 'Complete at least one field for this care log template.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    public static function buildBody(
        string $templateSlug,
        array $structured,
        ?string $outcomeStatus = null,
        ?string $linkedCarePlanSlug = null,
        ?string $linkedSupportObjective = null,
        ?string $linkedRiskSlug = null,
    ): string {
        $template = self::find($templateSlug);
        $lines = ['['.self::label($templateSlug).']'];

        if ($outcomeStatus) {
            $lines[] = 'Outcome: '.self::humanizeValue($outcomeStatus);
        }

        foreach ($template['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! array_key_exists($key, $structured)) {
                continue;
            }

            $value = $structured[$key];
            if (($field['type'] ?? '') === 'select' && isset($field['options'][$value])) {
                $value = $field['options'][$value];
            }

            $lines[] = ($field['label'] ?? $key).': '.$value;
        }

        if ($linkedCarePlanSlug) {
            $title = care_plan_catalogue_by_slug()[$linkedCarePlanSlug]['title']
                ?? Str::of($linkedCarePlanSlug)->replace('-', ' ')->title()->toString();
            $lines[] = 'Linked care plan: '.$title;
        }

        if ($linkedSupportObjective = trim((string) $linkedSupportObjective)) {
            $lines[] = 'Support objective: '.$linkedSupportObjective;
        }

        if ($linkedRiskSlug) {
            $title = risk_assessment_template($linkedRiskSlug)['title']
                ?? Str::of($linkedRiskSlug)->replace('-', ' ')->title()->toString();
            $lines[] = 'Linked risk assessment: '.$title;
        }

        return implode("\n", $lines);
    }

    private static function humanizeValue(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<int, array{label: string, value: string}>
     */
    public static function structuredSummary(string $templateSlug, array $structured): array
    {
        $template = self::find($templateSlug);
        if ($template === null) {
            return [];
        }

        $rows = [];
        foreach ($template['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! array_key_exists($key, $structured)) {
                continue;
            }

            $value = $structured[$key];
            if (($field['type'] ?? '') === 'select' && isset($field['options'][$value])) {
                $value = $field['options'][$value];
            }

            $rows[] = [
                'label' => $field['label'] ?? $key,
                'value' => (string) $value,
            ];
        }

        return $rows;
    }
}
