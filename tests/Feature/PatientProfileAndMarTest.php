<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientProfileAndMarTest extends TestCase
{
    use RefreshDatabase;

    private function createPatient(array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'url_key' => 'ac-'.random_int(10000, 99999),
            'slug' => 'test-patient',
            'name' => 'Test Patient',
            'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1 Support',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ], $overrides));
    }

    public function test_care_manager_can_update_patient_clinical_profile(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-12345',
            'capacity_status' => null,
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.profile.update', $patient->url_key), [
                'gp_name' => 'Dr Smith',
                'gp_practice' => 'Riverside Surgery',
                'capacity_status' => 'Has capacity',
                'allergy_details' => [
                    [
                        'allergen' => 'Penicillin',
                        'reaction' => 'Rash',
                        'severity' => 'Severe',
                        'verified_at' => '2026-01-15',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame('Dr Smith', $patient->gp_name);
        $this->assertSame('Has capacity', $patient->capacity_status);
        $this->assertCount(1, $patient->allergy_details);
    }

    public function test_mar_save_accepts_delayed_status_with_reason_and_rescheduled_time(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-99999']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Paracetamol',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Paracetamol',
                        'time' => '08:00',
                        'route' => 'Oral',
                        'dose' => '500mg',
                        'status' => 'Delayed',
                        'reason' => 'Service user asleep',
                        'rescheduled_time' => '11:30',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'status' => 'delayed',
            'reason' => 'Service user asleep',
        ]);

        $this->assertDatabaseHas('medication_reminders', [
            'patient_medication_id' => $medication->id,
            'dismissed' => false,
        ]);

        Carbon::setTestNow();
    }

    public function test_monthly_mar_chart_pdf_downloads(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-55555']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Aspirin',
            'active' => true,
            'scheduled_time' => '09:00:00',
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('patients.mar.monthly-chart.pdf', ['patient' => $patient->url_key]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_manager_can_clear_todays_mar(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-clear-mar']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Warfarin 5mg',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $manager->id,
            'status' => 'given',
            'administered_at' => now(),
            'source_mar_slug' => 'today-mar',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.mar.clear-today', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'voided_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows.0.status', 'Due')
                ->where('initialRows.0.by', '-'));
    }

    public function test_manager_can_deactivate_medication(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-deactivate-med']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin 500mg',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.medications.deactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertFalse($medication->fresh()->active);

        $this->actingAs($manager)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows', [])
                ->has('inactiveMedications', 1));

        $this->actingAs($manager)
            ->post(route('patients.medications.reactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertTrue($medication->fresh()->active);
    }

    public function test_deactivating_already_inactive_medication_redirects_gracefully(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-already-inactive']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Old Medication',
            'active' => false,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.medications.deactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertSessionHas('success');
    }

    public function test_medication_store_accepts_json_for_offline_sync(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-med-json']);

        $this->actingAs($manager)
            ->postJson(route('patients.medications.store', ['patient' => $patient->url_key]), [
                'generic_name' => 'Ibuprofen',
                'brand_name' => 'Nurofen',
                'route' => 'Oral',
                'dose_amount' => '200',
                'dose_unit' => 'mg',
                'frequency' => 'once_daily',
                'start_date' => '2026-06-01',
                'is_ongoing' => true,
                'prescriber_name' => 'Dr Patel',
                'prescriber_contact' => '01234 567890',
                'is_prn' => false,
                'is_controlled' => false,
                'is_time_critical' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patient_medications', [
            'patient_id' => $patient->id,
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Nurofen',
            'dose_amount' => '200',
            'dose_unit' => 'mg',
            'prescriber_name' => 'Dr Patel',
            'active' => true,
        ]);
    }

    public function test_care_worker_cannot_configure_medications(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-med-deny']);

        $this->actingAs($carer)
            ->post(route('patients.medications.store', ['patient' => $patient->url_key]), [
                'generic_name' => 'Paracetamol',
                'route' => 'Oral',
                'dose_amount' => '500',
                'dose_unit' => 'mg',
                'frequency' => 'once_daily',
                'start_date' => '2026-06-01',
                'prescriber_name' => 'Dr Smith',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('patient_medications', 0);
    }

    public function test_medication_setup_flags_allergy_cross_reference(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient([
            'url_key' => 'ac-med-allergy',
            'allergy_details' => [
                ['allergen' => 'Penicillin', 'reaction' => 'Rash', 'severity' => 'Severe'],
            ],
        ]);

        $payload = [
            'generic_name' => 'Amoxicillin',
            'route' => 'Oral',
            'dose_amount' => '250',
            'dose_unit' => 'mg',
            'frequency' => 'once_daily',
            'start_date' => '2026-06-01',
            'is_ongoing' => true,
            'prescriber_name' => 'Dr Jones',
            'is_prn' => false,
        ];

        $this->actingAs($manager)
            ->post(route('patients.medications.store', ['patient' => $patient->url_key]), $payload)
            ->assertSessionHasErrors(['allergy_conflicts']);

        $this->actingAs($manager)
            ->post(route('patients.medications.store', ['patient' => $patient->url_key]), array_merge($payload, [
                'allergy_acknowledged' => true,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patient_medications', [
            'patient_id' => $patient->id,
            'generic_name' => 'Amoxicillin',
        ]);
    }

    public function test_prn_setup_requires_indication_max_dose_and_interval(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-med-prn']);

        $this->actingAs($manager)
            ->post(route('patients.medications.store', ['patient' => $patient->url_key]), [
                'generic_name' => 'Morphine',
                'route' => 'Oral',
                'dose_amount' => '5',
                'dose_unit' => 'mg',
                'frequency' => 'once_daily',
                'start_date' => '2026-06-01',
                'is_ongoing' => true,
                'prescriber_name' => 'Dr Lee',
                'is_prn' => true,
                'is_controlled' => true,
            ])
            ->assertSessionHasErrors(['prn_indication', 'prn_max_daily_doses', 'prn_min_interval_minutes']);

        $this->actingAs($manager)
            ->post(route('patients.medications.store', ['patient' => $patient->url_key]), [
                'generic_name' => 'Morphine',
                'route' => 'Oral',
                'dose_amount' => '5',
                'dose_unit' => 'mg',
                'frequency' => 'once_daily',
                'start_date' => '2026-06-01',
                'is_ongoing' => true,
                'prescriber_name' => 'Dr Lee',
                'is_prn' => true,
                'is_controlled' => true,
                'prn_indication' => 'Breakthrough pain',
                'prn_max_daily_doses' => 4,
                'prn_min_interval_minutes' => 60,
            ])
            ->assertRedirect();

        $medication = PatientMedication::query()->where('patient_id', $patient->id)->first();
        $this->assertTrue($medication->is_prn);
        $this->assertTrue($medication->is_controlled);
        $this->assertSame(60, $medication->prn_min_interval_minutes);
    }

    public function test_super_admin_can_update_patient_rag_status_via_json(): void
    {
        $user = User::factory()->create(['primary_role' => 'super_admin', 'email_verified_at' => now()]);
        $patient = $this->createPatient([
            'url_key' => 'ac-rag-json',
            'rag_status' => 'green',
            'status' => 'GREEN',
        ]);

        $this->actingAs($user)
            ->patchJson(route('patients.rag-status', $patient->url_key), ['rag_status' => 'AMBER'])
            ->assertOk()
            ->assertJson(['message' => 'RAG status updated successfully.']);

        $patient->refresh();
        $this->assertSame('amber', $patient->rag_status);
        $this->assertSame('AMBER', $patient->status);
    }

    public function test_patient_directory_shows_rag_status_not_stale_status_field(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = $this->createPatient([
            'url_key' => 'ac-rag-sync',
            'name' => 'Louis Osei',
            'status' => 'AMBER',
            'rag_status' => 'green',
        ]);

        $this->actingAs($user)
            ->get(route('patients'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Patients')
                ->where('patients', fn ($patients) => collect($patients)->contains(
                    fn ($row) => ($row['urlKey'] ?? null) === $patient->url_key
                        && ($row['ragStatus'] ?? null) === 'GREEN'
                )));

        $this->actingAs($user)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('patient.ragStatus', 'green')
                ->where('patient.ragStatusLabel', 'GREEN'));
    }

    public function test_care_worker_cannot_clear_todays_mar(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-deny-clear']);

        $this->actingAs($carer)
            ->post(route('patients.mar.clear-today', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertForbidden();
    }

    public function test_mar_save_only_records_non_due_rows_and_by_column_scoped_per_time(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'name' => 'Eugene Osei',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-mar-by-slot']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Panadol (Paracetamol)',
            'active' => true,
            'scheduled_times' => ['08:00', '14:00', '22:00'],
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Panadol (Paracetamol)',
                        'time' => '08:00',
                        'route' => 'Oral',
                        'dose' => '500 mg',
                        'status' => 'Given',
                    ],
                    [
                        'id' => $medication->id,
                        'medicine' => 'Panadol (Paracetamol)',
                        'time' => '14:00',
                        'route' => 'Oral',
                        'dose' => '500 mg',
                        'status' => 'Due',
                    ],
                    [
                        'id' => $medication->id,
                        'medicine' => 'Panadol (Paracetamol)',
                        'time' => '22:00',
                        'route' => 'Oral',
                        'dose' => '500 mg',
                        'status' => 'Due',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('medication_administrations', 1);

        $this->actingAs($carer)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows.0.time', '08:00')
                ->where('initialRows.0.status', 'Given')
                ->where('initialRows.0.by', 'Eugene Osei')
                ->where('initialRows.1.time', '14:00')
                ->where('initialRows.1.status', 'Due')
                ->where('initialRows.1.by', '-')
                ->where('initialRows.2.time', '22:00')
                ->where('initialRows.2.status', 'Due')
                ->where('initialRows.2.by', '-'));
    }

    public function test_mar_save_updates_existing_slot_and_stats_count_scheduled_slots(): void
    {
        Carbon::setTestNow('2026-06-20 09:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-mar-stats']);
        $panadol = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Panadol (Paracetamol)',
            'active' => true,
            'scheduled_times' => ['08:00', '14:00', '22:00'],
            'scheduled_time' => '08:00:00',
        ]);
        $vitaminC = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Vitamin C',
            'active' => true,
            'scheduled_times' => ['06:00', '12:00', '18:00', '22:00'],
            'scheduled_time' => '06:00:00',
        ]);

        $givenRows = [
            ['id' => $panadol->id, 'medicine' => 'Panadol (Paracetamol)', 'time' => '08:00', 'route' => 'Oral', 'dose' => '500 mg', 'status' => 'Given'],
            ['id' => $vitaminC->id, 'medicine' => 'Vitamin C', 'time' => '06:00', 'route' => 'Oral', 'dose' => '100 mg', 'status' => 'Given'],
            ['id' => $vitaminC->id, 'medicine' => 'Vitamin C', 'time' => '12:00', 'route' => 'Oral', 'dose' => '100 mg', 'status' => 'Given'],
        ];

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => $givenRows,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('medication_administrations', 3);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => $givenRows,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('medication_administrations', 3);

        $this->actingAs($carer)
            ->get(route('patients.mar', ['patient' => $patient->url_key]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.givenToday', 3)
                ->where('stats.dueToday', 4));
    }

    public function test_mar_save_rejects_unconfigured_medication_rows(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-mar-unconfigured']);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'medicine' => 'Ad hoc Paracetamol',
                        'time' => '08:00',
                        'route' => 'Oral',
                        'dose' => '500mg',
                        'status' => 'Given',
                    ],
                ],
            ])
            ->assertSessionHasErrors(['mar']);

        $this->assertDatabaseCount('patient_medications', 0);
        $this->assertDatabaseCount('medication_administrations', 0);
    }

    public function test_refused_medication_requires_reason_and_generates_manager_care_alert(): void
    {
        Carbon::setTestNow('2026-06-19 12:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-refused-alert']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Metformin',
                        'time' => '08:00',
                        'status' => 'Refused',
                        'reason' => 'Service user declined',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'status' => 'refused',
            'reason' => 'Service user declined',
        ]);

        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('care-alerts'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('alerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'REFUSED MEDICATION'
                        && str_contains((string) ($alert['details'] ?? ''), 'Manager review required')
                )));

        Carbon::setTestNow();
    }

    public function test_prn_administration_records_indication_effectiveness_and_next_dose(): void
    {
        Carbon::setTestNow('2026-06-19 14:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-prn-record']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Oramorph',
            'active' => true,
            'is_prn' => true,
            'prn_indication' => 'Breakthrough pain',
            'prn_min_interval_minutes' => 60,
            'prn_max_daily_doses' => 4,
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.prn-administer', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'medication_id' => $medication->id,
                'prn_indication' => 'Breakthrough pain — score 7/10',
                'effectiveness_rating' => 'too_soon_to_assess',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $administration = MedicationAdministration::query()->first();
        $this->assertSame('prn_administered', $administration->status);
        $this->assertTrue($administration->is_prn_dose);
        $this->assertSame('Breakthrough pain — score 7/10', $administration->prn_indication);
        $this->assertSame('too_soon_to_assess', $administration->effectiveness_rating);
        $this->assertNotNull($administration->administered_at);
        $this->assertNotNull($administration->next_permissible_dose_at);
        $this->assertTrue($administration->next_permissible_dose_at->equalTo(now()->addHour()));

        Carbon::setTestNow();
    }

    public function test_given_records_staff_attribution_and_timestamp(): void
    {
        Carbon::setTestNow('2026-06-19 09:15:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'name' => 'Jane Carer',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-given-record']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Amlodipine',
            'active' => true,
            'scheduled_time' => '09:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Amlodipine',
                        'time' => '09:00',
                        'status' => 'Given',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_by_user_id' => $carer->id,
        ]);

        $administration = MedicationAdministration::query()->first();
        $this->assertTrue($administration->administered_at->equalTo(now()));

        $this->actingAs($carer)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows.0.status', 'Given')
                ->where('initialRows.0.by', 'Jane Carer')
                ->where('initialRows.0.administered_at', fn ($label) => is_string($label) && str_contains($label, '2026')));

        Carbon::setTestNow();
    }

    public function test_self_administered_records_timestamp_and_staff_attribution(): void
    {
        Carbon::setTestNow('2026-06-19 11:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'name' => 'Sam Carer',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-self-admin']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Inhaler',
            'active' => true,
            'scheduled_time' => '11:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Inhaler',
                        'time' => '11:00',
                        'status' => 'Self-Administered',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'status' => 'self_administered',
            'administered_by_user_id' => $carer->id,
        ]);

        $this->actingAs($carer)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows.0.status', 'Self-Administered')
                ->where('initialRows.0.by', 'Sam Carer'));

        Carbon::setTestNow();
    }

    public function test_refused_medication_pushes_manager_notification(): void
    {
        Carbon::setTestNow('2026-06-19 12:00:00');

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-push-alert']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Warfarin',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Warfarin',
                        'time' => '08:00',
                        'status' => 'Omitted',
                        'reason' => 'Nil by mouth',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());

        $this->actingAs($manager)
            ->get(route('api.staff-notifications'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.title', fn ($title) => str_contains((string) $title, 'OMITTED medication'));

        Carbon::setTestNow();
    }
}
