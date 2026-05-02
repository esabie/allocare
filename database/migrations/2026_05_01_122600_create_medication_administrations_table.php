<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('medication_administrations')) {
            return;
        }

        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('patient_medication_id')->constrained('patient_medications')->cascadeOnDelete();
            $table->foreignId('administered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->dateTime('administered_at')->nullable();
            $table->dateTime('scheduled_for')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_mar_slug')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};

