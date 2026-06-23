<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanModule;
use App\Models\PatientCarePlanSummary;
use App\Models\PatientCarePlanVersion;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->seedUser([
            'email' => 'admin@allocare.local',
            'username' => 'admin',
            'first_name' => 'Alex',
            'surname' => 'Admin',
            'primary_role' => 'super_admin',
        ]);

        $manager = $this->seedUser([
            'email' => 'manager@allocare.local',
            'username' => 'caremanager',
            'first_name' => 'Maria',
            'surname' => 'Manager',
            'primary_role' => 'care_manager',
        ]);

        $worker = $this->seedUser([
            'email' => 'worker@allocare.local',
            'username' => 'careworker',
            'first_name' => 'Sam',
            'surname' => 'Worker',
            'primary_role' => 'care_worker',
        ]);

        $winston = $this->seedPatient([
            'url_key' => 'ac-46420',
            'slug' => 'winston-geraldo',
            'name' => 'Winston Geraldo',
            'preferred_name' => 'Winston',
            'reference' => '#AC-46420',
            'nhs_number' => '4829921024',
            'dob' => '12/05/1948',
            'allergies' => ['Penicillin'],
            'allergy_details' => [
                ['allergen' => 'Penicillin', 'reaction' => 'Anaphylaxis', 'severity' => 'Severe'],
            ],
            'address' => '14 Oak Lane, Manchester, M20 4PQ',
            'phone' => '07123456789',
            'rag_status' => 'amber',
            'staffing_ratio' => '1:1 Support',
            'primary_diagnosis' => 'Complex care needs following stroke',
            'gp_name' => 'Dr Patel',
            'gp_practice' => 'Oak Lane Medical Centre',
        ]);

        $sarah = $this->seedPatient([
            'url_key' => 'ac-88210',
            'slug' => 'sarah-jenkins',
            'name' => 'Sarah Jenkins',
            'preferred_name' => 'Sarah',
            'reference' => '#AC-88210',
            'nhs_number' => '4829921025',
            'dob' => '12/05/1948',
            'allergies' => ['Penicillin'],
            'allergy_details' => [
                ['allergen' => 'Penicillin', 'reaction' => 'Rash', 'severity' => 'Moderate'],
            ],
            'address' => '8 Willow Close, Stockport, SK1 2AB',
            'phone' => '07987654321',
            'rag_status' => 'green',
            'staffing_ratio' => '1:2 Support',
            'primary_diagnosis' => 'Dementia with community support',
            'gp_name' => 'Dr Lewis',
            'gp_practice' => 'Willow Family Practice',
        ]);

        $this->seedCarePlanModules($winston, [
            'personal-care-and-dignity',
            'mobility-and-moving',
            'medication-support',
            'communication-passport',
        ], $manager);

        $this->seedCarePlanModules($sarah, [
            'nutrition-and-hydration',
            'wound-care',
            'about-me-person-centred-care-plan',
        ], $manager);

        $this->seedCarePlan($winston, 'personal-care-and-dignity', $this->personalCarePayload(), $manager);
        $this->seedCarePlan($winston, 'mobility-and-moving', $this->mobilityPayload(), $manager);
        $this->seedCarePlan($winston, 'medication-support', $this->genericCarePlanPayload('Medication support', 'Linked to eMAR chart'), $manager);

        $this->seedCarePlan($sarah, 'nutrition-and-hydration', $this->nutritionPayload(), $manager);
        $this->seedCarePlan($sarah, 'wound-care', $this->genericCarePlanPayload('Wound care', 'Lower leg dressing twice weekly'), $manager);

        $this->command?->info('');
        $this->command?->info('Local demo data seeded successfully.');
        $this->command?->info('');
        $this->command?->info('Login accounts (password for all: password)');
        $this->command?->info('Demo TOTP secret (all accounts): '.TwoFactorAuthentication::DEMO_SECRET);
        $this->command?->info('Use any authenticator app with this secret, or the current 6-digit code from it.');
        $this->command?->table(
            ['Role', 'Email', 'Use for'],
            [
                ['Super admin', $admin->email, 'Full access'],
                ['Care manager', $manager->email, 'Edit care plans & configure modules'],
                ['Care worker', $worker->email, 'Read-only care plan access'],
            ],
        );
        $this->command?->info('');
        $this->command?->info('Patients:');
        $this->command?->table(
            ['Name', 'URL key', 'Care plans'],
            [
                [$winston->name, $winston->url_key, 'Personal care, Mobility, Medication'],
                [$sarah->name, $sarah->url_key, 'Nutrition, Wound care'],
            ],
        );
    }

    private function seedUser(array $attributes): User
    {
        $firstName = $attributes['first_name'];
        $surname = $attributes['surname'];

        return User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                'name' => trim("{$firstName} {$surname}"),
                'first_name' => $firstName,
                'surname' => $surname,
                'username' => $attributes['username'],
                'password' => 'password',
                'primary_role' => $attributes['primary_role'],
                'account_status' => 'active',
                'email_verified_at' => now(),
                'mfa_enabled' => true,
                'two_factor_secret' => TwoFactorAuthentication::DEMO_SECRET,
                'two_factor_recovery_codes' => ['DEMO-1111', 'DEMO-2222', 'DEMO-3333', 'DEMO-4444'],
                'two_factor_confirmed_at' => now(),
            ],
        );
    }

    private function seedPatient(array $attributes): Patient
    {
        return Patient::query()->updateOrCreate(
            ['url_key' => $attributes['url_key']],
            array_merge($attributes, [
                'status' => 'ACTIVE',
                'avatar' => 'bg-slate-300',
            ]),
        );
    }

    private function seedCarePlanModules(Patient $patient, array $moduleSlugs, User $activatedBy): void
    {
        foreach ($moduleSlugs as $index => $slug) {
            PatientCarePlanModule::query()->updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'module_slug' => $slug,
                ],
                [
                    'sort_order' => $index,
                    'activated_by_user_id' => $activatedBy->id,
                    'activated_at' => now()->subDays(7),
                ],
            );
        }

        $patient->forceFill(['care_plan_modules_initialized' => true])->saveQuietly();
    }

    private function seedCarePlan(Patient $patient, string $planSlug, array $data, User $author): void
    {
        $submittedAt = now()->subDays(3);
        $reviewDue = $this->reviewDueFromData($data);

        $form = PatientCarePlanForm::query()->updateOrCreate(
            [
                'patient_slug' => $patient->url_key,
                'plan_slug' => $planSlug,
            ],
            [
                'data' => $data,
                'schema_version' => 2,
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'submitted_by_user_id' => $author->id,
                'updated_by_user_id' => $author->id,
            ],
        );

        PatientCarePlanSummary::query()->updateOrCreate(
            [
                'patient_slug' => $patient->url_key,
                'plan_slug' => $planSlug,
            ],
            [
                'snapshot_id' => $form->id,
                'schema_version' => 2,
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'submitted_by_user_id' => $author->id,
                'updated_by_user_id' => $author->id,
                'review_due_at' => $reviewDue,
                'key_fields' => [
                    'owner' => $data['owner'] ?? $data['plan_owner'] ?? 'Care Manager',
                    'review_due' => $reviewDue?->toDateString(),
                ],
                'data_excerpt' => mb_substr((string) ($data['what_matters_to_me'] ?? ''), 0, 250) ?: null,
            ],
        );

        if (PatientCarePlanVersion::query()
            ->where('patient_slug', $patient->url_key)
            ->where('plan_slug', $planSlug)
            ->exists()) {
            return;
        }

        PatientCarePlanVersion::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => $planSlug,
            'version_number' => 1,
            'data' => $data,
            'schema_version' => 2,
            'status' => 'submitted',
            'review_due_at' => $reviewDue,
            'change_summary' => 'Initial demo care plan version',
            'recorded_by_user_id' => $author->id,
            'recorded_at' => $submittedAt,
        ]);
    }

    private function personalCarePayload(): array
    {
        return [
            'cultural_or_religious_preferences' => 'Prefers morning wash before breakfast; observes Catholic traditions on Sundays.',
            'privacy_and_consent_requirements' => 'Knock and announce before entering bedroom; offer choice of male/female carer where possible.',
            'assistance_area_0' => true,
            'assistance_area_1' => true,
            'assistance_area_2' => false,
            'assistance_area_3' => true,
            'manual_handling_staff_count' => '2',
            'manual_handling_technique' => 'Stand aid for transfers',
            'manual_handling_sling_size' => 'Medium',
            'manual_handling_notes' => 'Use slide sheet for bed repositioning.',
            'skin_checks_required' => true,
            'what_matters_to_me' => 'Maintain dignity, familiar routines, and independence where safe.',
            'baseline_clinical_summary' => 'Requires support with washing, dressing, and oral care. History of pressure damage.',
            'smart_outcome_description' => 'Winston will feel clean and dignified after personal care, with skin intact.',
            'review_date' => now()->addMonth()->toDateString(),
            'plan_owner' => 'Maria Manager',
            'proactive_support' => 'Follow preferred morning routine; use familiar toiletries.',
            'active_steps' => 'Offer choices; maintain warmth; document skin checks.',
            'reactive_steps' => 'Stop if distressed; contact family advocate if refusal persists.',
            'equipment_required' => 'Stand aid, slide sheet, pressure cushion',
            'staff_competencies_training_required' => 'Manual handling, pressure area care',
            'monitoring_and_recording' => 'Daily skin check recorded on chart.',
            'escalation_pathway' => 'On-call manager; tissue viability nurse if skin breakdown.',
            'capacity_consent_note' => 'Has capacity for daily care decisions; best interest process documented if unwell.',
        ];
    }

    private function mobilityPayload(): array
    {
        return [
            'mobility_baseline_aids_used' => 'Frame indoors; wheelchair for longer distances.',
            'transfer_types' => 'Stand aid for bed to chair; two staff for hoisted transfers.',
            'falls_history_physio_programme' => 'Two falls in last year; physiotherapy twice weekly.',
            'hoist_type_and_sling' => 'Oxford mini hoist; medium sling.',
            'staff_transfers_positioning_limits' => 'No single-handed transfers; max 30 minutes in one position.',
            'what_matters_to_me' => 'Wants to walk to the garden when weather allows.',
            'baseline_clinical_summary' => 'Reduced mobility post-stroke; left-sided weakness.',
            'linked_risks_rag' => 'Fall risk, Frailty',
            'smart_outcomes' => 'Mobilise safely with agreed aids and maintain skin integrity.',
            'proactive_support' => 'Clear pathways; non-slip footwear; regular repositioning.',
            'active_steps' => 'Use gait belt; encourage active participation in transfers.',
            'reactive_steps' => 'If fall suspected, do not move; call 999 if injury.',
            'equipment_required' => 'Frame, wheelchair, hoist, sling',
            'staff_competencies_training_required' => 'Manual handling, falls prevention',
            'monitoring_and_recording' => 'Record mobility level each visit.',
            'escalation_pathway' => 'GP/physio if mobility declines.',
            'capacity_consent_note' => 'Capacity assessed as intact for mobility choices.',
            'review_due' => now()->addWeeks(6)->toDateString(),
            'owner' => 'Maria Manager',
        ];
    }

    private function nutritionPayload(): array
    {
        return [
            'must_score_weight_trend' => 'MUST 1; stable weight over 3 months.',
            'food_preferences_cultural_needs' => 'Soft diet; enjoys traditional British meals; vegetarian options preferred.',
            'iddsi_food_level' => 'Level 5 — Minced & Moist',
            'iddsi_drink_level_thickener_recipe' => 'Level 1 — Slightly Thick; 1 scoop per 200ml.',
            'feeding_posture_pacing_swallow_strategies' => 'Upright 90°; small spoonfuls; allow time to swallow.',
            'daily_fluid_target_ml' => '1500',
            'what_matters_to_me' => 'Enjoys tea with one sugar at mid-morning.',
            'baseline_clinical_summary' => 'Mild dysphagia; dietitian reviewed quarterly.',
            'linked_risks_rag' => 'Choking risk',
            'smart_outcomes' => 'Maintain hydration and weight within agreed range.',
            'proactive_support' => 'Prepare thickened fluids; supervise meals.',
            'active_steps' => 'Follow IDDSI levels; record intake.',
            'reactive_steps' => 'If coughing on fluids, stop and review SALT referral.',
            'equipment_required' => 'Thickener, modified cutlery',
            'staff_competencies_training_required' => 'Dysphagia awareness',
            'monitoring_and_recording' => 'Fluid balance chart daily.',
            'escalation_pathway' => 'Dietitian/SALT if intake drops below 1000ml.',
            'capacity_consent_note' => 'Capacity intact for meal choices.',
            'review_due' => now()->addMonth()->toDateString(),
            'owner' => 'Maria Manager',
        ];
    }

    private function genericCarePlanPayload(string $focusTitle, string $focusDetail): array
    {
        return [
            'what_matters_to_me' => 'Consistent familiar staff and clear communication.',
            'baseline_clinical_summary' => $focusDetail,
            'linked_risks_rag' => 'None',
            'smart_outcomes' => 'Support delivered safely in line with clinical guidance.',
            'proactive_support' => 'Follow agreed daily routine and monitoring schedule.',
            'active_steps' => 'Deliver care as documented; record observations.',
            'reactive_steps' => 'Escalate to on-call manager if condition changes.',
            'equipment_required' => 'As listed in risk assessment',
            'staff_competencies_training_required' => 'Role-specific training completed',
            'monitoring_and_recording' => 'Record in care notes each visit.',
            'escalation_pathway' => 'On-call manager → GP if required.',
            'capacity_consent_note' => 'Capacity reviewed within last 6 months.',
            'review_due' => now()->addMonth()->toDateString(),
            'owner' => 'Maria Manager',
            'primary_focus_0' => $focusTitle,
            'primary_focus_1' => 'Current support level and frequency',
            'primary_focus_2' => 'Equipment and aids in use',
            'primary_focus_3' => 'Monitoring requirements',
            'primary_focus_4' => 'Review and escalation triggers',
        ];
    }

    private function reviewDueFromData(array $data): ?Carbon
    {
        $raw = $data['review_due'] ?? $data['review_date'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
