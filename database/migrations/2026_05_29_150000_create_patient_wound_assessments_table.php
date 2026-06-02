<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_wound_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->string('wound_site');
            $table->string('wound_type')->nullable();
            $table->string('pressure_ulcer_grade')->nullable();
            $table->decimal('length_cm', 6, 2)->nullable();
            $table->decimal('width_cm', 6, 2)->nullable();
            $table->decimal('depth_cm', 6, 2)->nullable();
            $table->text('exudate')->nullable();
            $table->text('periwound_condition')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->text('dressing_type')->nullable();
            $table->text('pressure_regime')->nullable();
            $table->text('infection_signs')->nullable();
            $table->boolean('escalation_required')->default(false);
            $table->text('body_map_notes')->nullable();
            $table->text('plan_actions')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_wound_assessments');
    }
};
