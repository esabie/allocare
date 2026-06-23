<?php

namespace Tests\Feature;

use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use App\Models\PatientVital;
use App\Models\User;
use App\Support\News2Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\News2EscalationNotification;
use Tests\TestCase;

class News2ScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_news2_score_for_normal_observation_is_low_risk(): void
    {
        $result = News2Scoring::calculate([
            'respiration_rate' => 16,
            'spo2' => 98,
            'supplemental_oxygen' => false,
            'bp_systolic' => 120,
            'pulse' => 72,
            'temperature_celsius' => 36.8,
            'consciousness_level' => 'alert',
            'oxygen_scale' => 1,
        ]);

        $this->assertSame(0, $result['total_score']);
        $this->assertSame(News2Scoring::RISK_LOW, $result['risk_level']);
        $this->assertFalse($result['has_single_parameter_three']);
    }

    public function test_single_parameter_score_of_three_triggers_medium_risk(): void
    {
        $result = News2Scoring::calculate([
            'respiration_rate' => 26,
            'spo2' => 98,
            'supplemental_oxygen' => false,
            'bp_systolic' => 120,
            'pulse' => 72,
            'temperature_celsius' => 36.8,
            'consciousness_level' => 'alert',
            'oxygen_scale' => 1,
        ]);

        $this->assertSame(3, $result['total_score']);
        $this->assertTrue($result['has_single_parameter_three']);
        $this->assertSame(News2Scoring::RISK_MEDIUM, $result['risk_level']);
    }

    public function test_score_of_seven_or_more_is_high_risk(): void
    {
        $result = News2Scoring::calculate([
            'respiration_rate' => 26,
            'spo2' => 90,
            'supplemental_oxygen' => true,
            'bp_systolic' => 85,
            'pulse' => 125,
            'temperature_celsius' => 39.5,
            'consciousness_level' => 'confusion',
            'oxygen_scale' => 1,
        ]);

        $this->assertGreaterThanOrEqual(7, $result['total_score']);
        $this->assertSame(News2Scoring::RISK_HIGH, $result['risk_level']);
    }

    public function test_respiratory_care_plan_sets_oxygen_scale_two(): void
    {
        $patient = Patient::query()->create([
            'url_key' => 'pt-news2-scale',
            'slug' => 'pt-news2-scale',
            'name' => 'Scale Patient',
        ]);

        PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'respiratory-care',
            'data' => ['news2_oxygen_scale' => '2'],
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->assertSame(2, News2Scoring::resolvePatientOxygenScale($patient));
    }

    public function test_staff_can_record_news2_physical_observation(): void
    {
        Notification::fake();

        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $manager = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-news2-record',
            'slug' => 'pt-news2-record',
            'name' => 'NEWS2 Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.vitals.store', $patient->url_key), [
                'respiration_rate' => 26,
                'heart_rate' => 72,
                'bp_systolic' => 120,
                'spo2' => 98,
                'supplemental_oxygen' => false,
                'temperature_celsius' => 36.8,
                'consciousness_level' => 'alert',
            ])
            ->assertRedirect();

        $vital = PatientVital::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($vital);
        $this->assertSame(3, $vital->news2_score);
        $this->assertSame(News2Scoring::RISK_MEDIUM, $vital->news2_risk_level);
        $this->assertTrue($vital->news2_single_parameter_three);

        $this->assertDatabaseHas('care_journal_entries', [
            'patient_id' => $patient->id,
            'author_user_id' => $user->id,
        ]);

        Notification::assertSentTo($manager, News2EscalationNotification::class);
    }
}
