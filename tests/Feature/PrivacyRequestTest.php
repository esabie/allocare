<?php

namespace Tests\Feature;

use App\Models\DataRetentionSchedule;
use App\Models\Patient;
use App\Models\PatientWoundAssessment;
use App\Models\PrivacyErasureJob;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_gdpr_page(): void
    {
        $this->get(route('reports.gdpr'))->assertRedirect(route('login'));
    }

    public function test_care_worker_cannot_access_gdpr_page(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('reports.gdpr'))
            ->assertForbidden();
    }

    public function test_manager_can_log_sar_request(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-sar-1',
            'slug' => 'pt-sar-1',
            'name' => 'SAR Patient',
        ]);

        $this->actingAs($user)
            ->post(route('reports.gdpr.store'), [
                'request_type' => PrivacyRequest::TYPE_SUBJECT_ACCESS,
                'patient_id' => $patient->id,
                'request_details' => 'Formal subject access request received by email on 1 May 2026.',
            ])
            ->assertRedirect(route('reports.gdpr'));

        $this->assertDatabaseHas('privacy_requests', [
            'patient_id' => $patient->id,
            'request_type' => PrivacyRequest::TYPE_SUBJECT_ACCESS,
            'status' => 'pending',
            'requested_by_user_id' => $user->id,
        ]);
    }

    public function test_manager_can_export_sar_json_for_linked_patient(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-sar-export',
            'slug' => 'pt-sar-export',
            'name' => 'Export Patient',
            'nhs_number' => '9998887776',
        ]);

        $request = PrivacyRequest::query()->create([
            'request_type' => PrivacyRequest::TYPE_SUBJECT_ACCESS,
            'status' => 'in_progress',
            'patient_id' => $patient->id,
            'subject_name' => $patient->name,
            'request_details' => 'SAR for export test.',
            'requested_by_user_id' => $user->id,
            'due_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)
            ->get(route('reports.gdpr.sar-export', $request))
            ->assertOk()
            ->assertJsonPath('patient.url_key', 'pt-sar-export')
            ->assertJsonStructure(['exported_at', 'patient', 'observations', 'fluid_records', 'bowel_records']);
    }

    public function test_erasure_request_can_be_completed(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-erasure-1',
            'slug' => 'pt-erasure-1',
            'name' => 'Erasure Patient',
            'nhs_number' => '1234567890',
            'address' => '1 Test Street',
            'phone' => '07000000000',
        ]);

        $request = PrivacyRequest::query()->create([
            'request_type' => PrivacyRequest::TYPE_ERASURE,
            'status' => 'pending',
            'patient_id' => $patient->id,
            'subject_name' => $patient->name,
            'request_details' => 'Erasure request under Article 17.',
            'requested_by_user_id' => $user->id,
            'due_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)
            ->patch(route('reports.gdpr.update', $request), [
                'status' => 'completed',
                'outcome_notes' => 'Records anonymised in source systems; Allocare profile archived.',
            ])
            ->assertRedirect(route('reports.gdpr'));

        $request->refresh();
        $patient->refresh();

        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->completed_at);
        $this->assertDatabaseHas('privacy_erasure_jobs', [
            'privacy_request_id' => $request->id,
            'patient_id' => $patient->id,
            'status' => PrivacyErasureJob::STATUS_COMPLETED,
        ]);
        $this->assertSame('Erased Service User #'.$patient->id, $patient->name);
        $this->assertNull($patient->nhs_number);
        $this->assertSame('archived', $patient->status);
    }

    public function test_retention_schedule_can_be_marked_reviewed(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $schedule = DataRetentionSchedule::query()->create([
            'data_category' => 'Care records',
            'retention_period' => '8 years after discharge',
            'review_cycle_months' => 12,
            'last_reviewed_at' => now()->subMonths(14)->toDateString(),
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('reports.gdpr.retention.reviewed', $schedule))
            ->assertRedirect(route('reports.gdpr'));

        $schedule->refresh();
        $this->assertSame(now()->toDateString(), $schedule->last_reviewed_at->toDateString());
    }

    public function test_wound_assessment_accepts_base64_photo(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-wound-photo',
            'slug' => 'pt-wound-photo',
            'name' => 'Wound Photo Patient',
        ]);

        $png = base64_encode("\x89PNG\r\n\x1a\n".str_repeat('0', 32));

        $this->actingAs($user)
            ->postJson(route('patients.wound-care.store', $patient->url_key), [
                'wound_site' => 'Left heel',
                'photo_base64' => $png,
                'photo_filename' => 'heel.png',
            ])
            ->assertRedirect();

        $assessment = PatientWoundAssessment::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($assessment);
        $this->assertNotNull($assessment->photo_path);
    }

    public function test_manager_can_log_data_breach_with_72_hour_due_date(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $discovered = now()->subHours(80);

        $this->actingAs($user)
            ->post(route('reports.gdpr.store'), [
                'request_type' => PrivacyRequest::TYPE_DATA_BREACH,
                'request_details' => 'Unencrypted laptop lost containing limited care notes for three service users.',
                'discovered_at' => $discovered->toDateTimeString(),
                'ico_notification_required' => true,
                'individuals_affected_count' => 3,
                'breach_categories' => 'confidentiality, availability',
            ])
            ->assertRedirect(route('reports.gdpr'));

        $request = PrivacyRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(PrivacyRequest::TYPE_DATA_BREACH, $request->request_type);
        $this->assertTrue($request->ico_notification_required);
        $this->assertSame(3, $request->individuals_affected_count);
        $this->assertSame(
            $discovered->copy()->addHours(72)->toDateString(),
            $request->due_at->toDateString(),
        );
    }

    public function test_gdpr_page_flags_ico_review_overdue_for_open_breach(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);

        PrivacyRequest::query()->create([
            'request_type' => PrivacyRequest::TYPE_DATA_BREACH,
            'status' => 'in_progress',
            'subject_name' => 'Incident 42',
            'request_details' => 'Email mis-sent to wrong recipient.',
            'requested_by_user_id' => $user->id,
            'due_at' => now()->subDay(),
            'discovered_at' => now()->subHours(80),
            'ico_notification_required' => true,
            'breach_categories' => 'confidentiality',
        ]);

        $this->actingAs($user)
            ->get(route('reports.gdpr'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ReportsGdpr')
                ->has('requests.data', 1)
                ->where('requests.data.0.icoReviewOverdue', true));
    }

    public function test_manager_can_export_sar_pdf_for_linked_patient(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-sar-pdf',
            'slug' => 'pt-sar-pdf',
            'name' => 'PDF Export Patient',
            'nhs_number' => '1112223334',
        ]);

        $request = PrivacyRequest::query()->create([
            'request_type' => PrivacyRequest::TYPE_SUBJECT_ACCESS,
            'status' => 'in_progress',
            'patient_id' => $patient->id,
            'subject_name' => $patient->name,
            'request_details' => 'SAR for PDF export test.',
            'requested_by_user_id' => $user->id,
            'due_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)
            ->get(route('reports.gdpr.sar-export.pdf', $request))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_breach_update_can_record_ico_notification(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $request = PrivacyRequest::query()->create([
            'request_type' => PrivacyRequest::TYPE_DATA_BREACH,
            'status' => 'in_progress',
            'subject_name' => 'Breach update test',
            'request_details' => 'Ransomware attempt contained.',
            'requested_by_user_id' => $user->id,
            'due_at' => now()->addDay(),
            'discovered_at' => now()->subHours(10),
            'ico_notification_required' => true,
        ]);

        $notifiedAt = now()->subHours(2);

        $this->actingAs($user)
            ->patch(route('reports.gdpr.update', $request), [
                'status' => 'completed',
                'outcome_notes' => 'ICO notified; no further reporting required.',
                'ico_notified_at' => $notifiedAt->toDateTimeString(),
                'individuals_affected_count' => 0,
                'breach_categories' => 'availability',
            ])
            ->assertRedirect(route('reports.gdpr'));

        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->ico_notified_at);
        $this->assertSame(0, $request->individuals_affected_count);
    }
}
