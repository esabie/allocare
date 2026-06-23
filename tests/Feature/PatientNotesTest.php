<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function patient(array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'url_key' => 'pt-notes-'.random_int(1000, 9999),
            'slug' => 'pt-notes',
            'name' => 'Notes Patient',
        ], $overrides));
    }

    public function test_patient_notes_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = $this->patient(['url_key' => 'pt-notes-page']);

        $this->actingAs($user)
            ->get(route('patients.notes', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PatientNotes')
                ->where('patientSlug', $patient->url_key)
                ->where('patient.name', $patient->name)
            );
    }

    public function test_staff_can_create_patient_care_note_from_patient_record(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = $this->patient(['url_key' => 'pt-notes-create']);

        $this->actingAs($user)
            ->post(route('patients.notes.store', $patient->url_key), [
                'body' => 'Supported with personal care and fluids.',
            ])
            ->assertRedirect(route('patients.notes', $patient->url_key));

        $this->assertDatabaseHas('care_journal_entries', [
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Supported with personal care and fluids.',
        ]);
    }

    public function test_patient_notes_are_listed_newest_first(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = $this->patient(['url_key' => 'pt-notes-order']);

        CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Older note',
            'recorded_at' => now()->subDay(),
        ]);

        CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Newer note',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('patients.notes', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.body', 'Newer note')
                ->where('entries.1.body', 'Older note')
            );
    }

    public function test_patient_notes_search_filters_by_body(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = $this->patient(['url_key' => 'pt-notes-search']);

        CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Hydration offered',
            'recorded_at' => now(),
        ]);

        CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Mobility support provided',
            'recorded_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('patients.notes', ['patient' => $patient->url_key, 'q' => 'hydration']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 1)
                ->where('entries.0.body', 'Hydration offered')
            );
    }

    public function test_author_can_amend_own_note_with_audit_trail(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = $this->patient(['url_key' => 'pt-notes-amend']);

        $entry = CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Original note text',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('patients.notes.update', ['patient' => $patient->url_key, 'entry' => $entry->id]), [
                'body' => 'Amended note text',
            ])
            ->assertRedirect(route('patients.notes', $patient->url_key));

        $entry->refresh();
        $this->assertSame('Amended note text', $entry->body);
        $this->assertSame($user->id, $entry->amended_by_user_id);

        $event = AuditEvent::query()
            ->where('subject_type', 'care_journal')
            ->where('subject_key', (string) $entry->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('Amended care note', $event->description);
        $this->assertSame('Original note text', $event->previous_values['body'] ?? null);
        $this->assertSame('Amended note text', $event->new_values['body'] ?? null);
    }

    public function test_other_staff_cannot_amend_note_they_did_not_author(): void
    {
        $author = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
        $patient = $this->patient(['url_key' => 'pt-notes-forbidden']);

        $entry = CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $author->id,
            'body' => 'Private note',
            'recorded_at' => now(),
        ]);

        $this->actingAs($other)
            ->patch(route('patients.notes.update', ['patient' => $patient->url_key, 'entry' => $entry->id]), [
                'body' => 'Attempted change',
            ])
            ->assertForbidden();
    }

    public function test_care_manager_can_amend_any_note(): void
    {
        $author = User::factory()->create(['email_verified_at' => now()]);
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = $this->patient(['url_key' => 'pt-notes-manager']);

        $entry = CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $author->id,
            'body' => 'Needs manager correction',
            'recorded_at' => now(),
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.notes.update', ['patient' => $patient->url_key, 'entry' => $entry->id]), [
                'body' => 'Corrected by manager',
            ])
            ->assertRedirect();

        $this->assertSame('Corrected by manager', $entry->fresh()->body);
        $this->assertSame($manager->id, $entry->fresh()->amended_by_user_id);
    }
}
