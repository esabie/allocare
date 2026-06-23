<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patient_risk_assessments')) {
            return;
        }

        Schema::table('patient_risk_assessments', function (Blueprint $table) {
            $table->json('linked_care_plan_slugs')->nullable()->after('legal_restrictions');
            $table->json('linked_incident_ids')->nullable()->after('linked_care_plan_slugs');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patient_risk_assessments')) {
            return;
        }

        Schema::table('patient_risk_assessments', function (Blueprint $table) {
            $table->dropColumn(['linked_care_plan_slugs', 'linked_incident_ids']);
        });
    }
};
