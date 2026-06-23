<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_care_plan_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_care_plan_summaries', 'review_due_at')) {
                $table->date('review_due_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('patient_care_plan_summaries', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_care_plan_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('patient_care_plan_summaries', 'updated_by_user_id')) {
                $table->dropConstrainedForeignId('updated_by_user_id');
            }
            if (Schema::hasColumn('patient_care_plan_summaries', 'review_due_at')) {
                $table->dropColumn('review_due_at');
            }
        });
    }
};
