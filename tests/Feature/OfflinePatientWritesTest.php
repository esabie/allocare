<?php

namespace Tests\Feature;

use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflinePatientWritesTest extends TestCase
{
    use RefreshDatabase;

    private function createPatient(): Patient
    {
        return Patient::query()->create([
            'url_key' => 'ac-'.random_int(10000, 99999),
            'slug' => 'test-patient',
            'name' => 'Test Patient',
            'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1 Support',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ]);
    }

    public function test_journal_store_accepts_json_body_for_offline_sync(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient();

        $this->actingAs($user)
            ->postJson(route('care-notes.store'), [
                'patient_id' => $patient->id,
                'body' => 'Care note recorded while offline',
                'filter' => 'all',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('care_journal_entries', [
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Care note recorded while offline',
        ]);
        $this->assertSame(1, CareJournalEntry::query()->count());
    }

    public function test_patient_profile_update_accepts_json_patch_for_offline_sync(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-offline', 'gp_name' => null]);

        $this->actingAs($manager)
            ->patchJson(route('patients.profile.update', $patient->url_key), [
                'gp_name' => 'Dr Offline',
                'gp_practice' => 'Test Surgery',
            ])
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame('Dr Offline', $patient->gp_name);
        $this->assertSame('Test Surgery', $patient->gp_practice);
    }
}
