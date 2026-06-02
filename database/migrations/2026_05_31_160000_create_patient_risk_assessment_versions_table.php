<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_risk_assessment_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_risk_assessment_id')
                ->constrained(table: 'patient_risk_assessments', indexName: 'pra_ver_assessment_fk')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->constrained(indexName: 'pra_ver_patient_fk')
                ->cascadeOnDelete();
            $table->string('risk_slug', 64);
            $table->json('snapshot');
            $table->string('change_summary')->nullable();
            $table->foreignId('recorded_by_user_id')
                ->nullable()
                ->constrained(table: 'users', indexName: 'pra_ver_recorded_by_fk')
                ->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'risk_slug', 'recorded_at'], 'pra_ver_patient_slug_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_risk_assessment_versions');
    }
};
