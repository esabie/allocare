<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientWoundAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientWoundCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_wound_care_page(): void
    {
        $this->get(route('patients.wound-care', 'pt-wound-1'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_wound_care_page(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-wound-1',
            'slug' => 'pt-wound-1',
            'name' => 'Wound Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.wound-care', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientWoundCare')
                ->has('chartData.series'));
    }

    public function test_staff_can_record_wound_assessment_with_escalation(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-wound-2',
            'slug' => 'pt-wound-2',
            'name' => 'Escalation Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.wound-care.store', $patient->url_key), [
                'wound_site' => 'Sacrum',
                'wound_type' => 'Pressure ulcer',
                'pressure_ulcer_grade' => 'category_3',
                'length_cm' => 4.2,
                'width_cm' => 3.1,
                'pain_score' => 8,
                'infection_signs' => 'Increased exudate and odour',
                'escalation_required' => true,
            ])
            ->assertRedirect(route('patients.wound-care', $patient->url_key));

        $this->assertDatabaseHas('patient_wound_assessments', [
            'patient_id' => $patient->id,
            'wound_site' => 'Sacrum',
            'escalation_required' => true,
            'pressure_ulcer_grade' => 'category_3',
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function test_wound_site_is_required(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-wound-val',
            'slug' => 'pt-wound-val',
            'name' => 'Validation Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.wound-care', $patient->url_key))
            ->post(route('patients.wound-care.store', $patient->url_key), [
                'wound_site' => '',
            ])
            ->assertSessionHasErrors('wound_site');

        $this->assertDatabaseCount('patient_wound_assessments', 0);
    }

    public function test_measurement_chart_series_includes_recorded_assessments(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-wound-chart',
            'slug' => 'pt-wound-chart',
            'name' => 'Chart Patient',
        ]);

        PatientWoundAssessment::query()->create([
            'patient_id' => $patient->id,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now()->subDays(3),
            'wound_site' => 'Heel',
            'length_cm' => 2.0,
            'width_cm' => 1.5,
        ]);
        PatientWoundAssessment::query()->create([
            'patient_id' => $patient->id,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now()->subDay(),
            'wound_site' => 'Heel',
            'length_cm' => 1.8,
            'width_cm' => 1.4,
        ]);

        $this->actingAs($user)
            ->get(route('patients.wound-care', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chartData.series.length_cm', fn ($points) => count($points) === 2));
    }
}
