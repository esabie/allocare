<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PatientIncident;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_report_paginates_events(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        foreach (range(1, 30) as $index) {
            AuditEvent::query()->create([
                'user_id' => $user->id,
                'action' => 'update',
                'description' => 'Updated record '.$index,
                'subject_type' => 'patient',
                'subject_key' => 'patient-'.$index,
                'subject_label' => 'Patient '.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events.data', 25)
                ->where('events.total', 30)
                ->where('events.per_page', 25)
                ->where('events.current_page', 1));

        $this->actingAs($user)
            ->get(route('reports', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events.data', 5)
                ->where('events.current_page', 2));
    }

    public function test_incident_report_paginates_list(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-paginate',
            'slug' => 'pt-paginate',
            'name' => 'Paginate Patient',
            'reference' => 'AC-30001',
        ]);

        foreach (range(1, 30) as $index) {
            PatientIncident::query()->create([
                'patient_id' => $patient->id,
                'reported_by_user_id' => $user->id,
                'reference' => 'INC-2026-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'incident_title' => 'Incident '.$index,
                'data' => ['incidentTitle' => 'Incident '.$index],
                'submitted_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('reports.incidents'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('incidents.data', 25)
                ->where('incidents.total', 30));
    }
}
