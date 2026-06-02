<?php

namespace Tests\Feature;

use App\Models\DataRetentionSchedule;
use App\Models\MedicationStock;
use App\Models\MedicationStockMovement;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientWoundAssessment;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoadmapSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_mar_requires_different_witness_user(): void
    {
        $admin = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $witness = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now(), 'name' => 'Witness Carer']);
        $patient = Patient::query()->create(['url_key' => 'pt-cd', 'slug' => 'pt-cd', 'name' => 'CD Patient']);

        $med = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Morphine',
            'is_controlled' => true,
            'active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        MedicationStock::query()->create([
            'patient_medication_id' => $med->id,
            'balance' => 10,
            'unit' => 'doses',
        ]);

        $this->actingAs($admin)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'am-mar']), [
                'rows' => [[
                    'id' => $med->id,
                    'medicine' => $med->name,
                    'status' => 'Given',
                    'witness_user_id' => $admin->id,
                ]],
            ])
            ->assertSessionHasErrors('mar');

        $this->actingAs($admin)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'am-mar']), [
                'rows' => [[
                    'id' => $med->id,
                    'medicine' => $med->name,
                    'status' => 'Given',
                    'witness_user_id' => $witness->id,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $med->id,
            'witness_user_id' => $witness->id,
            'status' => 'given',
        ]);

        $stock = MedicationStock::query()->where('patient_medication_id', $med->id)->first();
        $this->assertSame(9.0, (float) $stock->balance);
        $this->assertDatabaseHas('medication_stock_movements', [
            'patient_medication_id' => $med->id,
            'movement_type' => MedicationStockMovement::TYPE_ADMINISTRATION,
        ]);
    }

    public function test_wound_assessment_accepts_photo_and_review_due(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-wound', 'slug' => 'pt-wound', 'name' => 'Wound Patient']);

        $this->actingAs($user)
            ->post(route('patients.wound-care.store', $patient->url_key), [
                'wound_site' => 'Sacrum',
                'body_map_region' => 'sacrum',
                'review_due_at' => now()->addDays(3)->toDateString(),
                'photo' => UploadedFile::fake()->image('wound.jpg'),
            ])
            ->assertRedirect();

        $assessment = PatientWoundAssessment::query()->first();
        $this->assertNotNull($assessment);
        $this->assertSame('sacrum', $assessment->body_map_region);
        $this->assertNotNull($assessment->photo_path);
        $this->assertNotNull($assessment->review_due_at);
        Storage::disk('public')->assertExists($assessment->photo_path);
    }

    public function test_gdpr_retention_and_privacy_notice_can_be_created(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('reports.gdpr.retention.store'), [
                'data_category' => 'Care records',
                'retention_period' => '8 years after last contact',
                'legal_basis' => 'Regulatory requirement',
            ])
            ->assertRedirect(route('reports.gdpr'));

        $this->actingAs($user)
            ->post(route('reports.gdpr.privacy-notices.store'), [
                'title' => 'Service user privacy notice',
                'version' => '2.0',
                'content' => 'This notice explains how we process personal data for care delivery purposes.',
                'is_active' => true,
            ])
            ->assertRedirect(route('reports.gdpr'));

        $this->assertDatabaseHas('data_retention_schedules', ['data_category' => 'Care records']);
        $this->assertDatabaseHas('privacy_notices', ['version' => '2.0', 'is_active' => true]);
    }

    public function test_staff_performance_and_clinical_outcomes_reports_load(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('reports.staff-performance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ReportsStaffPerformance'));

        $this->actingAs($user)
            ->get(route('reports.clinical-outcomes'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ReportsClinicalOutcomes'));
    }
}
