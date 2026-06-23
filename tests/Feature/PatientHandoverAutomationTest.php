<?php

namespace Tests\Feature;

use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\PatientHandover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientHandoverAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_endpoint_builds_summary_from_shift_activity(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-auto',
            'slug' => 'pt-ho-auto',
            'name' => 'Auto Handover Patient',
        ]);

        CareJournalEntry::query()->create([
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
            'body' => 'Patient settled well after lunch and engaged in activities.',
            'recorded_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('patients.handovers.generate', [
                'patient' => $patient->url_key,
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'periodStart',
                'periodEnd',
                'sections' => ['careNotes', 'observations', 'incidents', 'medications'],
                'timeline',
                'suggestedFields',
            ]);

        $this->assertNotEmpty($response->json('sections.careNotes'));
        $this->assertNotEmpty($response->json('timeline'));
    }

    public function test_staff_can_save_auto_generated_handover_with_snapshot(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-save-auto',
            'slug' => 'pt-ho-save-auto',
            'name' => 'Save Auto Patient',
        ]);

        $snapshot = [
            'periodStart' => now()->subHours(8)->toIso8601String(),
            'periodEnd' => now()->toIso8601String(),
            'timeline' => [
                [
                    'at' => now()->subHour()->toIso8601String(),
                    'atLabel' => now()->subHour()->format('d M Y, H:i'),
                    'category' => 'Care note',
                    'summary' => 'Follow-up required on hydration.',
                    'requiresFollowUp' => true,
                ],
            ],
            'suggestedFields' => [
                'handover_notes' => '• Follow-up required on hydration.',
            ],
        ];

        $this->actingAs($user)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
                'auto_generated' => true,
                'auto_snapshot' => $snapshot,
                'handover_notes' => '• Follow-up required on hydration.',
            ])
            ->assertRedirect(route('patients.handovers', $patient->url_key));

        $handover = PatientHandover::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($handover);
        $this->assertTrue($handover->auto_generated);
        $this->assertNotNull($handover->auto_snapshot);
        $this->assertNull($handover->acknowledged_at);
    }

    public function test_incoming_staff_can_acknowledge_handover(): void
    {
        $outgoing = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $incoming = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-ack',
            'slug' => 'pt-ho-ack',
            'name' => 'Ack Patient',
        ]);

        $handover = PatientHandover::query()->create([
            'patient_id' => $patient->id,
            'shift_type' => 'day',
            'shift_date' => now()->toDateString(),
            'author_user_id' => $outgoing->id,
            'handover_notes' => 'Please monitor fluid intake.',
            'recorded_at' => now(),
            'auto_generated' => true,
            'auto_snapshot' => ['timeline' => []],
        ]);

        $this->actingAs($incoming)
            ->post(route('patients.handovers.acknowledge', [
                'patient' => $patient->url_key,
                'handover' => $handover->id,
            ]))
            ->assertRedirect(route('patients.handovers', $patient->url_key));

        $handover->refresh();
        $this->assertNotNull($handover->acknowledged_at);
        $this->assertSame($incoming->id, $handover->acknowledged_by_user_id);
    }

    public function test_outgoing_author_cannot_acknowledge_own_handover(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-self-ack',
            'slug' => 'pt-ho-self-ack',
            'name' => 'Self Ack Patient',
        ]);

        $handover = PatientHandover::query()->create([
            'patient_id' => $patient->id,
            'shift_type' => 'day',
            'shift_date' => now()->toDateString(),
            'author_user_id' => $user->id,
            'handover_notes' => 'All stable.',
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('patients.handovers', $patient->url_key))
            ->post(route('patients.handovers.acknowledge', [
                'patient' => $patient->url_key,
                'handover' => $handover->id,
            ]))
            ->assertSessionHasErrors('acknowledge');

        $this->assertNull($handover->fresh()->acknowledged_at);
    }
}
