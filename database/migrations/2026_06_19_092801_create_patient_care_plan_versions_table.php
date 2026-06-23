<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_care_plan_versions')) {
            return;
        }

        Schema::create('patient_care_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->string('patient_slug');
            $table->string('plan_slug');
            $table->unsignedInteger('version_number');
            $table->json('data');
            $table->unsignedInteger('schema_version')->default(2);
            $table->string('status', 32)->default('submitted');
            $table->date('review_due_at')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['patient_slug', 'plan_slug', 'version_number'], 'cp_versions_patient_plan_version_unique');
            $table->index(['patient_slug', 'plan_slug', 'recorded_at']);
            $table->index('review_due_at');
        });

        if (!Schema::hasTable('patient_care_plan_forms')) {
            return;
        }

        $forms = DB::table('patient_care_plan_forms')->orderBy('id')->get();
        foreach ($forms as $form) {
            $data = json_decode((string) $form->data, true);
            if (!is_array($data)) {
                $data = [];
            }

            $reviewDue = $data['review_due'] ?? $data['review_date'] ?? null;

            DB::table('patient_care_plan_versions')->insert([
                'patient_slug' => $form->patient_slug,
                'plan_slug' => $form->plan_slug,
                'version_number' => 1,
                'data' => json_encode($data),
                'schema_version' => $form->schema_version ?? 2,
                'status' => $form->status ?? 'submitted',
                'review_due_at' => $reviewDue,
                'change_summary' => 'Initial version migrated from existing care plan record',
                'recorded_by_user_id' => $form->updated_by_user_id ?? $form->submitted_by_user_id,
                'recorded_at' => $form->updated_at ?? $form->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_care_plan_versions');
    }
};
