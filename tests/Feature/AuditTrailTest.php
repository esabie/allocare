<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    public function test_every_authenticated_request_creates_immutable_audit_event(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'));

        $event = AuditEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('read', $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertNotNull($event->ip_address);
        $this->assertNotNull($event->device_type);
        $this->assertNotNull($event->session_id);
        $this->assertNotNull($event->integrity_hash);
        $this->assertTrue(AuditTrail::verifyIntegrity($event));
        $this->assertSame('UTC', $event->created_at->timezoneName);
    }

    public function test_detect_operating_system_from_user_agent(): void
    {
        $this->assertSame('Windows', AuditTrail::detectOperatingSystem(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ));
        $this->assertSame('macOS', AuditTrail::detectOperatingSystem(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ));
        $this->assertSame('iOS', AuditTrail::detectOperatingSystem(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ));
        $this->assertSame('Android', AuditTrail::detectOperatingSystem(
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ));
        $this->assertSame('Unknown', AuditTrail::detectOperatingSystem(null));
    }

    public function test_audit_event_stores_operating_system_as_device_type(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->actingAs($user)->get(route('dashboard'));

        $event = AuditEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('Windows', $event->device_type);
    }

    public function test_audit_record_stores_before_and_after_values(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        AuditTrail::record(
            'updated',
            'Rescheduled visit',
            'schedule',
            '42',
            'Test Patient',
            [
                'before' => ['start_at' => '2026-05-24 09:00:00'],
                'after' => ['start_at' => '2026-05-24 14:00:00'],
            ],
        );

        $event = AuditEvent::query()->latest('id')->firstOrFail();
        $mapped = AuditTrail::mapForUi($event);

        $this->assertSame('update', $event->action);
        $this->assertSame(['start_at' => '2026-05-24 09:00:00'], $event->previous_values);
        $this->assertSame(['start_at' => '2026-05-24 14:00:00'], $event->new_values);
        $this->assertSame('Updated', $mapped['action_label']);
        $this->assertSame('Visit & schedule', $mapped['area_label']);
        $this->assertStringContainsString('Start time', $mapped['change_detail']);
    }

    public function test_audit_report_excludes_page_view_noise(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        AuditTrail::record('read', 'Viewed the dashboard', 'http', 'dashboard', 'Dashboard');
        AuditTrail::record('create', 'Recorded daily care note for Patient', 'care_journal', '1', 'Patient One');

        $events = AuditTrail::fetchAuditReportsForUi();

        $this->assertCount(1, $events);
        $this->assertSame('care_journal', $events[0]['subject_type']);
        $this->assertSame('Added', $events[0]['action_label']);
    }

    public function test_audit_events_are_immutable_after_insert(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        AuditTrail::record('create', 'Created immutable event', 'system', 'test-key', 'Test');

        $event = AuditEvent::query()->latest('id')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $event->description = 'Mutated description';
        $event->save();
    }

    public function test_audit_events_cannot_be_deleted(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        AuditTrail::record('create', 'Permanent event', 'system', 'permanent-key', 'Test');

        $event = AuditEvent::query()->latest('id')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $event->delete();
    }

    public function test_activity_logs_are_immutable(): void
    {
        $user = User::factory()->create([
            'email' => 't@t.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'));

        $log = UserActivityLog::query()->latest('id')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $log->path = '/mutated';
        $log->save();
    }

    public function test_blocked_medication_delete_attempt_is_audited(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = Patient::query()->create(['url_key' => 'pt-audit-del', 'slug' => 'pt-audit-del', 'name' => 'Delete Audit Patient']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Paracetamol',
            'active' => true,
        ]);

        $administration = MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->actingAs($user);

        try {
            $administration->delete();
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('audit_events', [
            'action' => 'delete_attempt',
            'subject_type' => 'medication_administration',
            'subject_key' => (string) $administration->id,
        ]);
    }
}
