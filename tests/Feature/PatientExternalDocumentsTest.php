<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientUploadedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientExternalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_patient_documents_page(): void
    {
        $this->get(route('patients.documents', 'pt-ext-1'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_documents_page_with_external_section(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-1',
            'slug' => 'pt-ext-1',
            'name' => 'External Docs Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.documents', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientDocuments')
                ->where('patientSlug', $patient->url_key)
                ->where('patient.name', 'External Docs Patient')
                ->has('externalDocuments')
                ->where('canUploadExternalDocuments', true));
    }

    public function test_staff_can_upload_external_care_plan_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-upload',
            'slug' => 'pt-ext-upload',
            'name' => 'Upload Patient',
        ]);

        $file = UploadedFile::fake()->create('commissioner-plan.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->post(route('patients.external-documents.store', $patient->url_key), [
                'title' => 'NHS Continuing Healthcare Plan',
                'source' => 'nhs_commissioner',
                'issued_at' => '2026-05-01',
                'notes' => 'Received by email',
                'file' => $file,
            ])
            ->assertRedirect(route('patients.documents', $patient->url_key));

        $document = PatientUploadedDocument::query()->first();
        $this->assertNotNull($document);
        $this->assertSame('NHS Continuing Healthcare Plan', $document->title);
        $this->assertSame('nhs_commissioner', $document->source);
        $this->assertSame($patient->id, $document->patient_id);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_staff_can_view_uploaded_pdf_inline(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-view',
            'slug' => 'pt-ext-view',
            'name' => 'View Patient',
        ]);

        $path = 'patient-external-documents/'.$patient->id.'/plan.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test content');

        $document = PatientUploadedDocument::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Authority Plan',
            'source' => 'local_authority',
            'file_path' => $path,
            'file_name' => 'plan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.external-documents.view', [
                'patient' => $patient->url_key,
                'document' => $document->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_staff_can_upload_word_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-word',
            'slug' => 'pt-ext-word',
            'name' => 'Word Patient',
        ]);

        $file = UploadedFile::fake()->create('plan.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($user)
            ->post(route('patients.external-documents.store', $patient->url_key), [
                'title' => 'Social Worker Plan',
                'source' => 'social_worker',
                'file' => $file,
            ])
            ->assertRedirect(route('patients.documents', $patient->url_key));

        $this->assertDatabaseCount('patient_uploaded_documents', 1);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-reject',
            'slug' => 'pt-ext-reject',
            'name' => 'Reject Patient',
        ]);

        $file = UploadedFile::fake()->create('photo.jpg', 120, 'image/jpeg');

        $this->actingAs($user)
            ->post(route('patients.external-documents.store', $patient->url_key), [
                'title' => 'Invalid Upload',
                'source' => 'other',
                'file' => $file,
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertDatabaseCount('patient_uploaded_documents', 0);
    }

    public function test_upload_rejects_files_larger_than_ten_megabytes(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-large',
            'slug' => 'pt-ext-large',
            'name' => 'Large File Patient',
        ]);

        $file = UploadedFile::fake()->create('large-plan.pdf', 11000, 'application/pdf');

        $this->actingAs($user)
            ->post(route('patients.external-documents.store', $patient->url_key), [
                'title' => 'Large Plan',
                'source' => 'local_authority',
                'file' => $file,
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertDatabaseCount('patient_uploaded_documents', 0);
    }

    public function test_staff_can_download_word_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-word-dl',
            'slug' => 'pt-ext-word-dl',
            'name' => 'Word Download Patient',
        ]);

        $path = 'patient-external-documents/'.$patient->id.'/plan.docx';
        Storage::disk('local')->put($path, 'docx-content');

        $document = PatientUploadedDocument::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Social Worker Plan',
            'source' => 'social_worker',
            'file_path' => $path,
            'file_name' => 'plan.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 256,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.external-documents.download', [
                'patient' => $patient->url_key,
                'document' => $document->id,
            ]))
            ->assertOk()
            ->assertDownload('plan.docx');
    }

    public function test_staff_can_download_uploaded_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-download',
            'slug' => 'pt-ext-download',
            'name' => 'Download Patient',
        ]);

        $path = 'patient-external-documents/'.$patient->id.'/plan.pdf';
        Storage::disk('local')->put($path, 'pdf-content');

        $document = PatientUploadedDocument::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Authority Plan',
            'source' => 'local_authority',
            'file_path' => $path,
            'file_name' => 'plan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 256,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.external-documents.download', [
                'patient' => $patient->url_key,
                'document' => $document->id,
            ]))
            ->assertOk()
            ->assertDownload('plan.pdf');
    }

    public function test_care_manager_can_delete_external_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ext-delete',
            'slug' => 'pt-ext-delete',
            'name' => 'Delete Patient',
        ]);

        $path = 'patient-external-documents/'.$patient->id.'/plan.pdf';
        Storage::disk('local')->put($path, 'pdf-content');

        $document = PatientUploadedDocument::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Old Plan',
            'source' => 'other',
            'file_path' => $path,
            'file_name' => 'plan.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('patients.external-documents.destroy', [
                'patient' => $patient->url_key,
                'document' => $document->id,
            ]))
            ->assertRedirect(route('patients.documents', $patient->url_key));

        $this->assertDatabaseMissing('patient_uploaded_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
