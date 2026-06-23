<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_journal_entries', function (Blueprint $table) {
            $table->string('template_slug', 64)->nullable()->after('body');
            $table->json('structured_data')->nullable()->after('template_slug');
            $table->string('outcome_status', 32)->nullable()->after('structured_data');
            $table->string('linked_care_plan_slug', 128)->nullable()->after('outcome_status');
            $table->text('linked_support_objective')->nullable()->after('linked_care_plan_slug');
            $table->string('linked_risk_assessment_slug', 128)->nullable()->after('linked_support_objective');

            $table->index(['patient_id', 'template_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('care_journal_entries', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'template_slug']);
            $table->dropColumn([
                'template_slug',
                'structured_data',
                'outcome_status',
                'linked_care_plan_slug',
                'linked_support_objective',
                'linked_risk_assessment_slug',
            ]);
        });
    }
};
