<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_vitals', function (Blueprint $table) {
            $table->decimal('temperature_celsius', 4, 1)->nullable()->after('spo2');
            $table->unsignedSmallInteger('bp_diastolic')->nullable()->after('bp_systolic');
            $table->decimal('blood_glucose_mmol', 5, 2)->nullable()->after('temperature_celsius');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('blood_glucose_mmol');
            $table->unsignedTinyInteger('pain_score')->nullable()->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('patient_vitals', function (Blueprint $table) {
            $table->dropColumn([
                'temperature_celsius',
                'bp_diastolic',
                'blood_glucose_mmol',
                'weight_kg',
                'pain_score',
            ]);
        });
    }
};
