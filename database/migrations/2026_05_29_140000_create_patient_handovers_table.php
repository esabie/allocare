<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('patient_schedule_id')->nullable()->constrained('patient_schedules')->nullOnDelete();
            $table->string('shift_type', 16);
            $table->date('shift_date');
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('presentation')->nullable();
            $table->text('care_delivered')->nullable();
            $table->text('medication_summary')->nullable();
            $table->text('risks_changes')->nullable();
            $table->text('handover_notes')->nullable();
            $table->text('sleep_summary')->nullable();
            $table->text('disturbances')->nullable();
            $table->text('night_medications')->nullable();
            $table->text('seizure_respiratory_events')->nullable();
            $table->text('morning_priorities')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'shift_date']);
            $table->index(['patient_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_handovers');
    }
};
