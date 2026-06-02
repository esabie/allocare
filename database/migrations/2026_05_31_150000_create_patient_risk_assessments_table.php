<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('risk_slug', 64);
            $table->string('risk_level', 16)->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('triggers')->nullable();
            $table->text('current_controls')->nullable();
            $table->text('mitigation_plan')->nullable();
            $table->string('owner_name')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->date('next_review_due_at')->nullable();
            $table->unsignedSmallInteger('review_cycle_months')->default(3);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'risk_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_risk_assessments');
    }
};
