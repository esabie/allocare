<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsEcmCommissionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_reports_viewer_can_open_ecm_commissioner_report(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = Patient::query()->create([
            'url_key' => 'ecm-patient-report',
            'slug' => 'ecm-patient-report',
            'name' => 'ECM Patient',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $carer->id,
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHours(2),
            'checked_in_at' => now()->subHours(3),
            'checked_out_at' => now()->subHours(2),
            'check_in_distance_metres' => 42,
            'check_out_distance_metres' => 58,
            'late_by_minutes' => 4,
            'left_early_by_minutes' => 0,
        ]);

        $this->actingAs($manager)
            ->get(route('reports.ecm-commissioner'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ReportsEcmCommissioner')
                ->has('rows', 1)
                ->has('stats')
                ->has('filters')
            );
    }

    public function test_ecm_commissioner_csv_export_downloads(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = Patient::query()->create([
            'url_key' => 'ecm-patient-export',
            'slug' => 'ecm-patient-export',
            'name' => 'ECM Export Patient',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $carer->id,
            'start_at' => now()->subDays(1)->setTime(8, 0),
            'end_at' => now()->subDays(1)->setTime(9, 0),
            'checked_in_at' => now()->subDays(1)->setTime(8, 3),
            'check_in_latitude' => 51.5001,
            'check_in_longitude' => -0.1201,
            'check_in_distance_metres' => 91,
            'late_by_minutes' => 3,
        ]);

        $response = $this->actingAs($manager)->get(route('reports.ecm-commissioner.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Allocare-ecm-commissioner-attendance-evidence-', (string) $response->headers->get('content-disposition'));
    }
}
