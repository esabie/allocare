<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_fluid_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedSmallInteger('fluid_intake_ml')->nullable();
            $table->unsignedSmallInteger('fluid_output_ml')->nullable();
            $table->string('fluid_type', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_fluid_records');
    }
};
