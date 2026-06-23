<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createPatient(): Patient
    {
        return Patient::query()->create([
            'url_key' => 'pt-med-report-'.random_int(1000, 9999),
            'slug' => 'pt-med-report',
            'name' => 'Report Patient',
        ]);
    }

    private function createAdministration(
        Patient $patient,
        PatientMedication $medication,
        array $overrides = [],
    ): MedicationAdministration {
        return MedicationAdministration::query()->create(array_merge([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
            'scheduled_for' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    public function test_medication_report_includes_scheduled_time_and_timeliness(): void
    {
        Carbon::setTestNow('2026-06-19 12:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient();

        $routineMed = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Routine Med',
            'active' => true,
            'is_time_critical' => false,
        ]);

        $criticalMed = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Critical Med',
            'active' => true,
            'is_time_critical' => true,
        ]);

        $prnMed = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'PRN Med',
            'active' => true,
            'is_prn' => true,
        ]);

        $scheduled = Carbon::parse('2026-06-19 08:00:00');

        $this->createAdministration($patient, $routineMed, [
            'administered_at' => $scheduled->copy()->addMinutes(10),
            'scheduled_for' => $scheduled,
        ]);

        $this->createAdministration($patient, $routineMed, [
            'administered_at' => $scheduled->copy()->addMinutes(45),
            'scheduled_for' => $scheduled,
        ]);

        $this->createAdministration($patient, $criticalMed, [
            'administered_at' => $scheduled->copy()->addMinutes(20),
            'scheduled_for' => $scheduled,
        ]);

        $this->createAdministration($patient, $routineMed, [
            'status' => 'delayed',
            'administered_at' => $scheduled->copy()->addHour(),
            'scheduled_for' => $scheduled,
            'reason' => 'Asleep',
        ]);

        $this->createAdministration($patient, $prnMed, [
            'administered_at' => now(),
            'scheduled_for' => null,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('reports.medications', [
                'from' => '2026-06-19',
                'to' => '2026-06-19',
            ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ReportsMedications')
                ->has('administrations.data', 5)
                ->where('administrations.data', function ($rows) {
                    $timeliness = collect($rows)->pluck('timeliness')->sort()->values()->all();

                    return $timeliness === ['Delayed', 'Late', 'Late', 'N/A', 'On time'];
                })
            );

        Carbon::setTestNow();
    }

    public function test_medication_report_csv_includes_scheduled_time_and_timeliness_columns(): void
    {
        Carbon::setTestNow('2026-06-19 12:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient();
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Aspirin',
            'active' => true,
        ]);

        $scheduled = Carbon::parse('2026-06-19 09:00:00');
        $this->createAdministration($patient, $medication, [
            'administered_at' => $scheduled->copy()->addMinutes(5),
            'scheduled_for' => $scheduled,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('reports.medications.export.csv', [
                'from' => '2026-06-19',
                'to' => '2026-06-19',
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Scheduled Time', $content);
        $this->assertStringContainsString('Timeliness', $content);
        $this->assertStringContainsString('On time', $content);
        $this->assertStringContainsString('19 Jun 2026 09:00', $content);

        Carbon::setTestNow();
    }

    public function test_medication_report_pdf_downloads_with_timeliness(): void
    {
        Carbon::setTestNow('2026-06-19 12:00:00');

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient();
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin',
            'active' => true,
        ]);

        $scheduled = Carbon::parse('2026-06-19 07:00:00');
        $this->createAdministration($patient, $medication, [
            'administered_at' => $scheduled->copy()->addMinutes(20),
            'scheduled_for' => $scheduled,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('reports.medications.export.pdf', [
                'from' => '2026-06-19',
                'to' => '2026-06-19',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        Carbon::setTestNow();
    }
}
