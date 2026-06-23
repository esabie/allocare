<?php

namespace App\Support;

use App\Models\Patient;
use Illuminate\Validation\Rule;

class PatientRegistration
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function careGroups(): array
    {
        return config('patient_registration.care_groups', []);
    }

    public static function careGroupValues(): array
    {
        return array_column(self::careGroups(), 'value');
    }

    public static function completionDueHours(): int
    {
        return max(1, (int) config('patient_registration.completion_due_hours', 72));
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeRules(): array
    {
        $careGroups = self::careGroupValues();

        return [
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'primary_diagnosis' => ['nullable', 'string', 'max:500'],
            'severe_allergies' => ['nullable', 'string', 'max:500'],
            'allergy_details' => ['nullable', 'array'],
            'allergy_details.*.allergen' => ['required_with:allergy_details', 'string', 'max:255'],
            'allergy_details.*.reaction' => ['nullable', 'string', 'max:255'],
            'allergy_details.*.severity' => ['nullable', 'string', 'max:100'],
            'allergy_details.*.verified_at' => ['nullable', 'date'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'gp_name' => ['nullable', 'string', 'max:255'],
            'gp_practice' => ['nullable', 'string', 'max:255'],
            'gp_phone' => ['nullable', 'string', 'max:50'],
            'primary_language' => ['nullable', 'string', 'max:100'],
            'interpreter_required' => ['nullable', 'boolean'],
            'capacity_status' => ['nullable', 'string', 'max:255'],
            'best_interest_decision' => ['nullable', 'string', 'max:2000'],
            'information_sharing_consent' => ['nullable', 'string', 'max:255'],
            'dols_lps_status' => ['nullable', 'string', 'max:255'],
            'dnacpr_status' => ['nullable', 'string', 'max:255'],
            'mobility_aids' => ['nullable', 'string', 'max:500'],
            'hoist_type' => ['nullable', 'string', 'max:255'],
            'sling_size' => ['nullable', 'string', 'max:100'],
            'equipment_notes' => ['nullable', 'string', 'max:2000'],
            'environmental_notes' => ['nullable', 'string', 'max:2000'],
            'social_worker_name' => ['nullable', 'string', 'max:255'],
            'social_worker_contact' => ['nullable', 'string', 'max:100'],
            'commissioner_name' => ['nullable', 'string', 'max:255'],
            'commissioner_contact' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'rag_status' => ['nullable', 'string', 'in:green,amber,red'],
            'staffing_ratio' => ['nullable', 'string', 'max:50'],
            'care_group' => ['required', 'string', Rule::in($careGroups)],
            'address_line_1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'next_of_kin' => ['required', 'string', 'max:255'],
            'next_of_kin_tel' => ['required', 'string', 'max:50'],
            'next_of_kin_email' => ['nullable', 'email', 'max:255'],
            'other_relevant_people' => ['nullable', 'string', 'max:1000'],
            'social_services_number' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'between:1,500'],
            'height_m' => ['nullable', 'numeric', 'between:0.3,3'],
            'start_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'photo_base64' => ['nullable', 'string', 'max:7000000'],
            'photo_filename' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'nhs_number' => ['nullable', 'string', 'regex:/^\d{10}$/', 'unique:patients,nhs_number'],
            'dob' => ['required', 'string', 'max:50'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:GREEN,AMBER,RED'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizeStorePayload(array $payload): array
    {
        $nhs = preg_replace('/\D+/', '', (string) ($payload['nhs_number'] ?? '')) ?? '';
        $payload['nhs_number'] = strlen($nhs) === 10 ? $nhs : null;
        $payload['rag_status'] = $payload['rag_status'] ?? 'green';
        $payload['staffing_ratio'] = ($payload['staffing_ratio'] ?? null) ?: '1:1 Support';

        return $payload;
    }

    /**
     * @return array<int, array{column: string, label: string}>
     */
    public static function deferredProfileFields(): array
    {
        return config('patient_registration.deferred_profile_fields', []);
    }

    public static function profileFieldIsMissing(Patient $patient, string $column): bool
    {
        $value = $patient->{$column};

        if (in_array($column, ['weight_kg', 'height_m'], true)) {
            return $value === null || (float) $value <= 0;
        }

        if ($column === 'photo_path') {
            return $value === null || trim((string) $value) === '';
        }

        return $value === null || trim((string) $value) === '';
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function outstandingFields(Patient $patient): array
    {
        $outstanding = [];

        foreach (self::deferredProfileFields() as $field) {
            $column = $field['column'];
            if (self::profileFieldIsMissing($patient, $column)) {
                $outstanding[] = [
                    'key' => $column,
                    'label' => $field['label'],
                ];
            }
        }

        return $outstanding;
    }

    public static function isProfileIncomplete(Patient $patient): bool
    {
        return self::hasOutstandingFields($patient);
    }

    public static function profileCompletionIsOverdue(Patient $patient): bool
    {
        if (! self::isProfileIncomplete($patient) || $patient->profile_completion_due_at === null) {
            return false;
        }

        return $patient->profile_completion_due_at->isPast();
    }

    public static function hasOutstandingFields(Patient $patient): bool
    {
        return self::outstandingFields($patient) !== [];
    }

    public static function careGroupLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (self::careGroups() as $group) {
            if ($group['value'] === $value) {
                return $group['label'];
            }
        }

        return str_replace('_', ' ', ucwords($value, '_'));
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    public static function careGroupsForValues(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::careGroupLabel($value) ?? $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function employeeCareGroupRules(): array
    {
        return [
            'assigned_care_groups' => ['required', 'array', 'min:1'],
            'assigned_care_groups.*' => ['string', Rule::in(self::careGroupValues())],
        ];
    }

    /**
     * @param  mixed  $input
     * @return array<int, string>
     */
    public static function normalizeAssignedCareGroups(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $allowed = self::careGroupValues();

        return array_values(array_unique(array_filter(
            array_map(static fn ($value) => trim((string) $value), $input),
            static fn (string $value) => $value !== '' && in_array($value, $allowed, true),
        )));
    }

    public static function syncProfileCompletion(Patient $patient): void
    {
        if (! self::hasOutstandingFields($patient)) {
            if ($patient->profile_completed_at === null) {
                $patient->forceFill(['profile_completed_at' => now()])->save();
            }

            return;
        }

        if ($patient->profile_completed_at !== null) {
            $patient->forceFill(['profile_completed_at' => null])->save();
        }
    }
}
