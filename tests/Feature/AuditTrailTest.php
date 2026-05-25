<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_care_manager_can_view_audit_reports(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ReportsAudit'));
    }

    public function test_allowed_email_can_view_activity_logs(): void
    {
        $user = User::factory()->create([
            'email' => 't@t.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.activity-logs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AdminActivityLogs'));
    }

    public function test_care_manager_cannot_view_activity_logs_without_allowed_email(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email' => 'manager@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.activity-logs'))
            ->assertForbidden();
    }

    public function test_authenticated_request_is_recorded_in_activity_logs(): void
    {
        $user = User::factory()->create([
            'email' => 't@t.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'method' => 'GET',
        ]);
    }

    public function test_patient_registration_creates_audit_event_not_activity_noise(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('patients.store'), [
                'title' => 'Mr.',
                'first_name' => 'Audit',
                'last_name' => 'Patient',
                'date_of_birth' => '1980-01-01',
                'gender' => 'Male',
                'primary_diagnosis' => 'Test diagnosis',
                'rag_status' => 'green',
                'staffing_ratio' => '1:1 Support',
                'address_line_1' => '1 Test Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'phone_number' => '07123456789',
                'email_address' => 'audit.patient.'.uniqid().'@example.com',
                'next_of_kin' => 'Kin Person',
                'next_of_kin_tel' => '07987654321',
                'next_of_kin_email' => 'kin.'.uniqid().'@example.com',
                'social_services_number' => 'SS-12345',
                'weight_kg' => 70,
                'height_m' => 1.75,
                'start_date' => '2026-01-01',
                'name' => 'Mr. Audit Patient',
                'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'dob' => '01/01/1980',
                'address' => '1 Test Street, London, SW1A 1AA',
                'phone' => '07123456789',
                'status' => 'ACTIVE',
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('patient.jpg'),
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('patients'));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $admin->id,
            'action' => 'created',
            'subject_type' => 'patient',
        ]);

        $event = AuditEvent::query()->where('subject_type', 'patient')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString('Registered patient', $event->description);

        $this->assertGreaterThan(0, UserActivityLog::query()->count());
    }
}
