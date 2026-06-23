<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\MedicationEscalationLog;
use App\Models\MedicationReminder;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MedicationEscalationTest extends TestCase
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

    public function test_missed_medication_notifies_manager(): void
    {
        Carbon::setTestNow('2026-06-20 10:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-missed-med']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin',
            'active' => true,
            'scheduled_times' => ['08:00'],
            'scheduled_time' => '08:00:00',
        ]);

        MedicationReminder::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'due_at' => Carbon::parse('2026-06-20 08:00:00'),
        ]);

        Artisan::call('medications:process-escalations');

        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_MISSED,
        ]);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());

        $this->actingAs($manager)
            ->get(route('api.staff-notifications'))
            ->assertOk()
            ->assertJsonPath('items.0.title', 'Missed medication — Test Patient');
    }

    public function test_time_critical_missed_escalates_after_threshold(): void
    {
        Carbon::setTestNow('2026-06-20 09:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-tc-missed']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Insulin',
            'active' => true,
            'is_time_critical' => true,
            'scheduled_times' => ['08:00'],
            'scheduled_time' => '08:00:00',
        ]);

        MedicationReminder::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'due_at' => Carbon::parse('2026-06-20 08:00:00'),
        ]);

        Artisan::call('medications:process-escalations');

        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_MISSED,
        ]);
        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_TIME_CRITICAL_MISSED,
        ]);

        $this->assertGreaterThanOrEqual(2, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_prn_max_daily_dose_reached_flags_manager(): void
    {
        Carbon::setTestNow('2026-06-20 14:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-prn-overuse']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Oramorph',
            'active' => true,
            'is_prn' => true,
            'prn_indication' => 'Breakthrough pain',
            'prn_max_daily_doses' => 2,
            'prn_min_interval_minutes' => 60,
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $carer->id,
            'status' => 'prn_administered',
            'administered_at' => Carbon::parse('2026-06-20 10:00:00'),
            'source_mar_slug' => 'today-mar',
            'is_prn_dose' => true,
            'prn_indication' => 'Pain',
            'effectiveness_rating' => 'effective',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.prn-administer', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'medication_id' => $medication->id,
                'prn_indication' => 'Breakthrough pain',
                'effectiveness_rating' => 'effective',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_PRN_OVERUSE,
        ]);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_prn_overuse_blocked_attempt_notifies_manager(): void
    {
        Carbon::setTestNow('2026-06-20 16:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-prn-blocked']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Oramorph',
            'active' => true,
            'is_prn' => true,
            'prn_indication' => 'Breakthrough pain',
            'prn_max_daily_doses' => 1,
            'prn_min_interval_minutes' => 60,
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $carer->id,
            'status' => 'prn_administered',
            'administered_at' => Carbon::parse('2026-06-20 10:00:00'),
            'source_mar_slug' => 'today-mar',
            'is_prn_dose' => true,
            'prn_indication' => 'Pain',
            'effectiveness_rating' => 'effective',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.prn-administer', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'medication_id' => $medication->id,
                'prn_indication' => 'Breakthrough pain',
                'effectiveness_rating' => 'effective',
            ])
            ->assertSessionHasErrors(['prn']);

        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_PRN_OVERUSE_BLOCKED,
        ]);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_rescue_medication_administration_triggers_escalation_and_flash(): void
    {
        Carbon::setTestNow('2026-06-20 11:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-rescue-med']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Buccal Midazolam',
            'generic_name' => 'Midazolam',
            'active' => true,
            'is_rescue' => true,
            'is_prn' => true,
            'prn_indication' => 'Prolonged seizure',
            'prn_max_daily_doses' => 2,
            'prn_min_interval_minutes' => 120,
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.prn-administer', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'medication_id' => $medication->id,
                'prn_indication' => 'Prolonged seizure activity',
                'effectiveness_rating' => 'partially_effective',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('rescue_escalation')
            ->assertRedirect();

        $this->assertDatabaseHas('medication_escalation_logs', [
            'patient_medication_id' => $medication->id,
            'escalation_type' => MedicationEscalationLog::TYPE_RESCUE_ADMINISTRATION,
        ]);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }
}
