<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_vitals', function (Blueprint $table) {
            $table->unsignedSmallInteger('respiration_rate')->nullable()->after('heart_rate');
            $table->boolean('supplemental_oxygen')->default(false)->after('spo2');
            $table->unsignedTinyInteger('oxygen_saturation_scale')->default(1)->after('supplemental_oxygen');
            $table->string('consciousness_level', 16)->nullable()->after('temperature_celsius');
            $table->unsignedTinyInteger('news2_score')->nullable()->after('consciousness_level');
            $table->string('news2_risk_level', 16)->nullable()->after('news2_score');
            $table->boolean('news2_single_parameter_three')->default(false)->after('news2_risk_level');
            $table->json('news2_component_scores')->nullable()->after('news2_single_parameter_three');
            $table->text('news2_escalation_guidance')->nullable()->after('news2_component_scores');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedTinyInteger('news2_oxygen_scale')->default(1)->after('rag_status');
        });
    }

    public function down(): void
    {
        Schema::table('patient_vitals', function (Blueprint $table) {
            $table->dropColumn([
                'respiration_rate',
                'supplemental_oxygen',
                'oxygen_saturation_scale',
                'consciousness_level',
                'news2_score',
                'news2_risk_level',
                'news2_single_parameter_three',
                'news2_component_scores',
                'news2_escalation_guidance',
            ]);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('news2_oxygen_scale');
        });
    }
};
