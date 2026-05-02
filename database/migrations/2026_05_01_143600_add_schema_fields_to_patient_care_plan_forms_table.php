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
        Schema::table('patient_care_plan_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_care_plan_forms', 'schema_version')) {
                $table->unsignedInteger('schema_version')->default(1)->after('data');
            }
            if (!Schema::hasColumn('patient_care_plan_forms', 'status')) {
                $table->string('status', 32)->default('submitted')->after('schema_version');
                $table->index('status');
            }
            if (!Schema::hasColumn('patient_care_plan_forms', 'submitted_by_user_id')) {
                $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
                $table->index('submitted_by_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_care_plan_forms', function (Blueprint $table) {
            if (Schema::hasColumn('patient_care_plan_forms', 'submitted_by_user_id')) {
                $table->dropConstrainedForeignId('submitted_by_user_id');
            }
            if (Schema::hasColumn('patient_care_plan_forms', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('patient_care_plan_forms', 'schema_version')) {
                $table->dropColumn('schema_version');
            }
        });
    }
};
