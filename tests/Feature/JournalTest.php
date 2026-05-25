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

    public function test_guest_cannot_access_journal(): void
    {
        $this->get(route('journal'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_journal_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('journal'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Journal'));
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
            ->post(route('journal.store'), [
                'patient_id' => $patient->id,
                'body' => 'Assisted with morning personal care and hydration.',
                'filter' => 'all',
            ])
            ->assertRedirect(route('journal', ['filter' => 'all']));

        $this->assertDatabaseHas('care_journal_entries', [
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Assisted with morning personal care and hydration.',
        ]);
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
            ->get(route('journal'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journal')
                ->where('entries.0.body', 'Newer note')
                ->where('entries.1.body', 'Older note'));
    }
}
