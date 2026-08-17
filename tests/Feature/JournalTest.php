<?php

namespace Tests\Feature;

use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function validDailySupportPayload(int $patientId, string $shiftType = 'day'): array
    {
        return [
            'patient_id' => $patientId,
            'shift_type' => $shiftType,
            'log_date' => now()->toDateString(),
            'structured_data' => [
                'slots' => [
                    '07_08' => ['initials' => 'EO', 'notes' => 'Assisted with morning personal care and hydration.'],
                ],
                'incident' => 'no',
                'shift_comments' => 'No concerns.',
            ],
            'filter' => 'all',
        ];
    }

    public function test_guest_cannot_access_journal(): void
    {
        $this->get(route('care-notes'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_journal_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('care-notes'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journal')
                ->has('dailySupportCareLog.daySlots')
                ->has('dailySupportCareLog.nightSlots'));
    }

    public function test_staff_can_record_daily_care_note(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-1',
            'slug' => 'pt-journal-1',
            'name' => 'Jane Example',
        ]);

        $this->actingAs($user)
            ->post(route('care-notes.store'), $this->validDailySupportPayload($patient->id))
            ->assertRedirect(route('care-notes', ['filter' => 'all']));

        $this->assertDatabaseHas('care_journal_entries', [
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'shift_type' => 'day',
            'template_slug' => 'daily_support_care_log',
        ]);
    }

    public function test_journal_store_returns_json_for_api_clients(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-json',
            'slug' => 'pt-journal-json',
            'name' => 'JSON Patient',
        ]);

        $payload = $this->validDailySupportPayload($patient->id, 'night');
        $payload['structured_data']['slots'] = [
            '20_21' => ['initials' => 'EO', 'notes' => 'Settled for the evening.'],
        ];

        $this->actingAs($user)
            ->postJson(route('care-notes.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entry.shiftType', 'night')
            ->assertJsonPath('entry.templateSlug', 'daily_support_care_log');
    }

    public function test_care_note_requires_completed_shift_log(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-shift',
            'slug' => 'pt-journal-shift',
            'name' => 'Shift Patient',
        ]);

        $this->actingAs($user)
            ->post(route('care-notes.store'), [
                'patient_id' => $patient->id,
                'shift_type' => 'day',
                'log_date' => now()->toDateString(),
                'structured_data' => [
                    'slots' => [],
                    'incident' => 'no',
                ],
                'filter' => 'all',
            ])
            ->assertSessionHasErrors('structured_data');
    }

    public function test_journal_lists_entries_in_reverse_chronological_order(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-2',
            'slug' => 'pt-journal-2',
            'name' => 'John Example',
        ]);

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
            ->get(route('care-notes'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journal')
                ->where('entries.0.body', 'Newer note')
                ->where('entries.1.body', 'Older note'));
    }

    public function test_cannot_resubmit_occupied_time_slot(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-occupied',
            'slug' => 'pt-journal-occupied',
            'name' => 'Occupied Patient',
        ]);

        $payload = $this->validDailySupportPayload($patient->id);

        $this->actingAs($user)
            ->post(route('care-notes.store'), $payload)
            ->assertRedirect(route('care-notes', ['filter' => 'all']));

        $this->actingAs($user)
            ->post(route('care-notes.store'), $payload)
            ->assertSessionHasErrors('structured_data');
    }

    public function test_occupied_slots_endpoint_returns_recorded_slots(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-journal-slots',
            'slug' => 'pt-journal-slots',
            'name' => 'Slots Patient',
        ]);

        $payload = $this->validDailySupportPayload($patient->id);

        $this->actingAs($user)
            ->post(route('care-notes.store'), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->getJson(route('care-notes.occupied-slots', [
                'patient_id' => $patient->id,
                'log_date' => $payload['log_date'],
                'shift_type' => 'day',
            ]))
            ->assertOk()
            ->assertJsonPath('occupiedSlots.07_08.notes', 'Assisted with morning personal care and hydration.')
            ->assertJsonPath('occupiedSlots.07_08.label', '07:00-08:00');
    }
}
