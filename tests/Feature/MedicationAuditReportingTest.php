<?php

namespace Tests\Feature;

use App\Models\EmarWeeklyAudit;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationAuditReportingTest extends TestCase
{
    use RefreshDatabase;

    private function createPatient(array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'url_key' => 'ac-'.random_int(10000, 99999),
            'slug' => 'test-patient',
            'name' => 'Test Patient',
            'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1 Support',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ], $overrides));
    }

    public function test_medication_administration_records_cannot_be_deleted(): void
    {
        $patient = $this->createPatient();
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Aspirin',
            'active' => true,
        ]);

        $administration = MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $administration->delete();
    }

    public function test_cqc_exception_report_pdf_exports_only_refused_omitted_delayed(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-exceptions']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin',
            'active' => true,
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
            'scheduled_for' => now(),
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'refused',
            'reason' => 'Service user declined',
            'scheduled_for' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('reports.medications.exceptions.export.pdf', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_patient_medication_history_page_lists_voided_records(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-history']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Warfarin',
            'active' => true,
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
            'voided_at' => now(),
            'void_reason' => 'Cleared from today\'s eMAR by manager',
        ]);

        $this->actingAs($carer)
            ->get(route('patients.mar.history', ['patient' => $patient->url_key]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientMedicationHistory')
                ->has('administrations', 1)
                ->where('administrations.0.voided', true));
    }

    public function test_clinical_administrator_can_sign_off_weekly_emar_audit(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('reports.emar-weekly-audit.sign-off'), [
                'week_start' => '2026-06-16',
                'notes' => 'All exceptions reviewed with team.',
                'checklist' => [
                    'exceptions_reviewed' => true,
                    'controlled_register_reconciled' => true,
                    'prn_usage_reviewed' => true,
                    'time_critical_escalations_reviewed' => true,
                    'action_plan_documented' => true,
                ],
            ])
            ->assertRedirect(route('reports.emar-weekly-audit', ['week' => '2026-06-15']))
            ->assertSessionHas('success');

        $this->assertEquals(1, EmarWeeklyAudit::query()
            ->whereDate('week_start', '2026-06-15')
            ->where('reviewed_by_user_id', $manager->id)
            ->count());
    }

    public function test_monthly_mar_pdf_accepts_month_parameter(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-month-pdf']);

        $this->actingAs($admin)
            ->get(route('patients.mar.monthly-chart.pdf', [
                'patient' => $patient->url_key,
                'month' => '2026-05',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
