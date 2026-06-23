<?php

namespace Tests\Feature;

use App\Models\MedicationStock;
use App\Models\MedicationStockMovement;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledDrugManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_self_administered_requires_witness_and_deducts_stock(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $witness = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-cd-self', 'slug' => 'pt-cd-self', 'name' => 'CD Self Patient']);

        $med = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Morphine',
            'is_controlled' => true,
            'active' => true,
        ]);

        MedicationStock::query()->create([
            'patient_medication_id' => $med->id,
            'balance' => 5,
            'unit' => 'doses',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [[
                    'id' => $med->id,
                    'medicine' => $med->name,
                    'time' => '10:00',
                    'status' => 'Self-Administered',
                    'witness_user_id' => $witness->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $med->id,
            'status' => 'self_administered',
            'witness_user_id' => $witness->id,
        ]);

        $this->assertSame(4.0, (float) MedicationStock::query()->where('patient_medication_id', $med->id)->value('balance'));

        Carbon::setTestNow();
    }

    public function test_handover_requires_controlled_drug_reconciliation(): void
    {
        Carbon::setTestNow('2026-06-19 18:00:00');

        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $witness = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-cd-ho', 'slug' => 'pt-cd-ho', 'name' => 'CD Handover Patient']);

        $med = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Diazepam',
            'is_controlled' => true,
            'active' => true,
        ]);

        MedicationStock::query()->create([
            'patient_medication_id' => $med->id,
            'balance' => 8,
            'unit' => 'tablets',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
                'presentation' => 'Settled.',
            ])
            ->assertSessionHasErrors('controlled_reconciliations');

        $this->actingAs($carer)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
                'presentation' => 'Settled.',
                'controlled_reconciliations' => [[
                    'medication_id' => $med->id,
                    'counted_balance' => 8,
                    'witness_user_id' => $witness->id,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patient_handovers', [
            'patient_id' => $patient->id,
            'controlled_drug_reconciliation_complete' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_stock_discrepancy_notifies_manager_and_creates_care_alert(): void
    {
        Carbon::setTestNow('2026-06-19 18:00:00');

        $manager = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $witness = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-cd-disc', 'slug' => 'pt-cd-disc', 'name' => 'CD Discrepancy Patient']);

        $med = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Oxycodone',
            'is_controlled' => true,
            'active' => true,
        ]);

        MedicationStock::query()->create([
            'patient_medication_id' => $med->id,
            'balance' => 10,
            'unit' => 'doses',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'night',
                'shift_date' => now()->toDateString(),
                'sleep_summary' => 'Restful night.',
                'controlled_reconciliations' => [[
                    'medication_id' => $med->id,
                    'counted_balance' => 7,
                    'witness_user_id' => $witness->id,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(7.0, (float) MedicationStock::query()->where('patient_medication_id', $med->id)->value('balance'));
        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());

        $this->actingAs($witness)
            ->get(route('care-alerts'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('alerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'CD STOCK DISCREPANCY'
                )));

        Carbon::setTestNow();
    }

    public function test_destruction_records_are_permanent(): void
    {
        $manager = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $witness = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-cd-dest', 'slug' => 'pt-cd-dest', 'name' => 'CD Destruction Patient']);

        $med = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Fentanyl',
            'is_controlled' => true,
            'active' => true,
        ]);

        MedicationStock::query()->create([
            'patient_medication_id' => $med->id,
            'balance' => 3,
            'unit' => 'patches',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.medications.stock', ['patient' => $patient->url_key, 'medication' => $med->id]), [
                'movement_type' => 'destruction',
                'quantity' => 1,
                'witness_user_id' => $witness->id,
                'notes' => 'Expired patch destroyed',
            ])
            ->assertRedirect();

        $movement = MedicationStockMovement::query()->where('movement_type', 'destruction')->first();
        $this->assertTrue($movement->is_permanent_record);

        $this->expectException(\RuntimeException::class);
        $movement->delete();
    }
}
