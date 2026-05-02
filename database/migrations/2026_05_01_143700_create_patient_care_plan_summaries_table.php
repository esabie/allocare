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
        if (Schema::hasTable('patient_care_plan_summaries')) {
            return;
        }

        Schema::create('patient_care_plan_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('patient_slug');
            $table->string('plan_slug');
            $table->foreignId('snapshot_id')->nullable()->constrained('patient_care_plan_forms')->nullOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('status', 32)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('key_fields')->nullable();
            $table->text('data_excerpt')->nullable();
            $table->timestamps();

            $table->unique(['patient_slug', 'plan_slug']);
            $table->index(['patient_slug', 'plan_slug']);
            $table->index('status');
            $table->index('submitted_at');
            $table->index('submitted_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_care_plan_summaries');
    }
};
