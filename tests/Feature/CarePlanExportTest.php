<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\PatientCarePlanExport;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanModule;
use App\Models\PatientCarePlanVersion;
use App\Models\PatientUploadedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CarePlanExportTest extends TestCase
{
    use RefreshDatabase;

    private function safeguardingPayload(): array
    {
        return [
            'what_matters_to_me' => 'Dignity and safety at home.',
            'baseline_clinical_summary' => 'Requires safeguarding oversight.',
            'linked_risks_rag' => 'Safeguarding concern',
            'smart_outcomes' => 'Remain safe in the community.',
            'proactive_support' => 'Structured visits and observation.',
            'active_steps' => 'Follow safeguarding plan.',
            'reactive_steps' => 'Contact safeguarding lead.',
            'equipment_required' => 'None',
            'staff_competencies_training_required' => 'Safeguarding level 2',
            'monitoring_and_recording' => 'Daily notes',
            'escalation_pathway' => 'On-call manager then MASH',
            'capacity_consent_note' => 'Best interest process documented.',
            'review_due' => now()->addMonth()->toDateString(),
            'owner' => 'Care Manager',
            'primary_focus_0' => 'Previous MARAC involvement',
            'primary_focus_1' => 'Current risks',
            'primary_focus_2' => 'Agency contacts',
            'primary_focus_3' => 'Recording requirements',
            'primary_focus_4' => 'Review schedule',
        ];
    }

    private function seedPatientWithSafeguardingPlan(string $urlKey = 'pt-export'): array
    {
        $patient = Patient::query()->create([
            'url_key' => $urlKey,
            'slug' => $urlKey,
            'name' => 'Export Patient',
            'reference' => 'AC-10001',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'data' => $this->safeguardingPayload(),
            'schema_version' => 2,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        PatientCarePlanVersion::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'version_number' => 1,
            'data' => $this->safeguardingPayload(),
            'schema_version' => 2,
            'status' => 'submitted',
            'review_due_at' => now()->addMonth(),
            'change_summary' => 'Initial version',
            'recorded_at' => now(),
        ]);

        return [$patient];
    }

    public function test_care_worker_cannot_export_care_plan_package(): void
    {
        [$patient] = $this->seedPatientWithSafeguardingPlan('pt-export-deny');
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('patients.careplans.export.pdf', $patient->url_key))
            ->assertForbidden();
    }

    public function test_care_manager_can_export_full_care_plan_pdf(): void
    {
        [$patient] = $this->seedPatientWithSafeguardingPlan('pt-export-pdf');
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        $response = $this->actingAs($user)
            ->get(route('patients.careplans.export.pdf', $patient->url_key));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $export = PatientCarePlanExport::query()->first();
        $this->assertNotNull($export);
        $this->assertSame($patient->id, $export->patient_id);
        $this->assertSame($user->id, $export->exported_by_user_id);
        $this->assertSame(PatientCarePlanExport::FORMAT_PDF, $export->format);
        $this->assertSame(PatientCarePlanExport::SCOPE_FULL_PACKAGE, $export->scope);
        $this->assertSame(['safeguarding'], $export->plan_slugs);
        $this->assertSame(['safeguarding' => 1], $export->version_snapshot);

        $audit = AuditEvent::query()->where('action', 'export')->first();
        $this->assertNotNull($audit);
        $this->assertSame($export->export_reference, $audit->subject_key);
    }

    public function test_care_manager_can_export_single_section_pdf(): void
    {
        [$patient] = $this->seedPatientWithSafeguardingPlan('pt-export-section');
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('patients.careplans.section.export.pdf', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $export = PatientCarePlanExport::query()->first();
        $this->assertSame(PatientCarePlanExport::SCOPE_SINGLE_SECTION, $export->scope);
        $this->assertSame(['safeguarding'], $export->plan_slugs);
    }

    public function test_zip_export_includes_supporting_documents(): void
    {
        Storage::fake('local');

        [$patient] = $this->seedPatientWithSafeguardingPlan('pt-export-zip');
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        $file = UploadedFile::fake()->create('la-care-plan.pdf', 120, 'application/pdf');
        $path = $file->store("patient-external-documents/{$patient->id}", 'local');

        PatientUploadedDocument::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Local Authority Support Plan',
            'source' => PatientUploadedDocument::SOURCE_LOCAL_AUTHORITY,
            'issued_at' => now()->subWeek(),
            'file_path' => $path,
            'file_name' => 'la-care-plan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120000,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('patients.careplans.export.zip', $patient->url_key));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $downloadFile = $response->baseResponse->getFile();
        $this->assertNotNull($downloadFile);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($downloadFile->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('supporting-documents/la-care-plan.pdf'));
        $this->assertNotFalse($zip->locateName('AlloCare-CarePlan-export-patient.pdf', ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR));
        $zip->close();

        $export = PatientCarePlanExport::query()->first();
        $this->assertSame(PatientCarePlanExport::FORMAT_ZIP, $export->format);
        $this->assertCount(1, $export->external_document_ids);
    }

    public function test_care_plans_index_exposes_export_permission_for_managers(): void
    {
        [$patient] = $this->seedPatientWithSafeguardingPlan('pt-export-ui');
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('patients.careplans', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientCarePlans')
                ->where('canExportCarePlans', true));
    }
}
