<?php

namespace Tests\Feature;

use App\Models\StaffCompetency;
use App\Models\StaffDocument;
use App\Models\StaffSupervision;
use App\Models\StaffTrainingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsComplianceTrainingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_reports_viewer_can_open_compliance_training_report(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $staff = User::factory()->create([
            'primary_role' => 'care_worker',
            'dbs_expiry_date' => now()->addDays(45)->toDateString(),
            'dbs_status' => 'clear',
        ]);

        StaffTrainingRecord::query()->create([
            'user_id' => $staff->id,
            'course_name' => 'Medication Administration',
            'status' => 'completed',
            'completed_date' => now()->subMonths(3)->toDateString(),
            'expiry_date' => now()->addDays(20)->toDateString(),
        ]);

        StaffCompetency::query()->create([
            'user_id' => $staff->id,
            'skill_name' => 'Moving and Handling',
            'status' => 'competent',
            'next_review_date' => now()->addDays(25)->toDateString(),
        ]);

        StaffSupervision::query()->create([
            'user_id' => $staff->id,
            'scheduled_date' => now()->subDays(20)->toDateString(),
            'completed_date' => now()->subDays(20)->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
            'status' => 'completed',
        ]);

        StaffDocument::query()->create([
            'user_id' => $staff->id,
            'title' => 'Right to Work',
            'category' => 'id',
            'file_path' => 'staff-documents/test.pdf',
            'file_name' => 'test.pdf',
            'file_size' => 12345,
            'expiry_date' => now()->addDays(80)->toDateString(),
        ]);

        $this->actingAs($manager)
            ->get(route('reports.compliance-training'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ReportsComplianceTraining')
                ->has('stats')
                ->has('staffRows')
                ->has('actions')
            );
    }

    public function test_compliance_training_report_csv_export_downloads(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'primary_role' => 'care_worker',
            'dbs_expiry_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($manager)->get(route('reports.compliance-training.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Allocare-compliance-training-report-', (string) $response->headers->get('content-disposition'));
    }

    public function test_compliance_training_report_pdf_export_downloads(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'primary_role' => 'care_worker',
            'dbs_expiry_date' => now()->addDays(30)->toDateString(),
        ]);

        $response = $this->actingAs($manager)->get(route('reports.compliance-training.export.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Allocare-compliance-training-report-', (string) $response->headers->get('content-disposition'));
    }
}
