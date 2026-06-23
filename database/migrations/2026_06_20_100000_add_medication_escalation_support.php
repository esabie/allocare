<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->boolean('is_rescue')->default(false)->after('is_time_critical');
        });

        Schema::create('medication_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_medication_id')->constrained('patient_medications')->cascadeOnDelete();
            $table->foreignId('medication_administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->string('escalation_type');
            $table->timestamp('slot_due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['patient_medication_id', 'escalation_type', 'slot_due_at'], 'med_escalation_slot_unique');
            $table->unique(['medication_administration_id', 'escalation_type'], 'med_escalation_admin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_escalation_logs');

        Schema::table('patient_medications', function (Blueprint $table) {
            $table->dropColumn('is_rescue');
        });
    }
};
