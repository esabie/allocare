<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patient_risk_assessments')) {
            return;
        }

        Schema::table('patient_risk_assessments', function (Blueprint $table) {
            $table->text('risk_statement')->nullable()->after('status');
            $table->text('proactive_controls')->nullable()->after('triggers');
            $table->text('active_controls')->nullable()->after('proactive_controls');
            $table->text('reactive_controls')->nullable()->after('active_controls');
            $table->text('monitoring_requirements')->nullable()->after('reactive_controls');
            $table->text('escalation_pathway')->nullable()->after('monitoring_requirements');
            $table->text('capacity_consent_notes')->nullable()->after('escalation_pathway');
            $table->text('legal_restrictions')->nullable()->after('capacity_consent_notes');
        });

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'low')
            ->update(['risk_level' => 'green']);

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'moderate')
            ->update(['risk_level' => 'amber']);

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'high')
            ->update(['risk_level' => 'red']);

        DB::table('patient_risk_assessments')
            ->whereNull('active_controls')
            ->whereNotNull('current_controls')
            ->update(['active_controls' => DB::raw('current_controls')]);

        DB::table('patient_risk_assessments')
            ->whereNull('escalation_pathway')
            ->whereNotNull('mitigation_plan')
            ->update(['escalation_pathway' => DB::raw('mitigation_plan')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('patient_risk_assessments')) {
            return;
        }

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'green')
            ->update(['risk_level' => 'low']);

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'amber')
            ->update(['risk_level' => 'moderate']);

        DB::table('patient_risk_assessments')
            ->where('risk_level', 'red')
            ->update(['risk_level' => 'high']);

        Schema::table('patient_risk_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'risk_statement',
                'proactive_controls',
                'active_controls',
                'reactive_controls',
                'monitoring_requirements',
                'escalation_pathway',
                'capacity_consent_notes',
                'legal_restrictions',
            ]);
        });
    }
};
