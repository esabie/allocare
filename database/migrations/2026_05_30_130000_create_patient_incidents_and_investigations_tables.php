<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('incident_title')->nullable();
            $table->date('incident_date')->nullable();
            $table->string('incident_time', 16)->nullable();
            $table->string('location')->nullable();
            $table->json('data');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('incident_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_incident_id')->unique()->constrained('patient_incidents')->cascadeOnDelete();
            $table->string('investigation_status', 32)->default('pending');
            $table->foreignId('investigator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('investigation_completed_at')->nullable();
            $table->text('investigation_summary')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->boolean('riddor_reportable')->default(false);
            $table->string('riddor_category', 64)->nullable();
            $table->timestamp('riddor_reported_at')->nullable();
            $table->string('riddor_reference', 128)->nullable();
            $table->boolean('safeguarding_concern')->default(false);
            $table->boolean('safeguarding_referral_made')->default(false);
            $table->timestamp('safeguarding_referral_at')->nullable();
            $table->string('safeguarding_authority', 255)->nullable();
            $table->string('safeguarding_reference', 128)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_investigations');
        Schema::dropIfExists('patient_incidents');
    }
};
