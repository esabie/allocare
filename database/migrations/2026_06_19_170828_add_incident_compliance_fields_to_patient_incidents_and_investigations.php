<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_incidents', function (Blueprint $table) {
            $table->string('severity', 16)->nullable()->after('location');
            $table->string('category', 64)->nullable()->after('severity');
            $table->string('sub_category', 128)->nullable()->after('category');
        });

        Schema::table('incident_investigations', function (Blueprint $table) {
            $table->string('corrective_action_owner', 255)->nullable()->after('corrective_actions');
            $table->text('recurrence_prevention')->nullable()->after('corrective_action_owner');
            $table->text('investigation_outcome')->nullable()->after('investigation_summary');
        });
    }

    public function down(): void
    {
        Schema::table('patient_incidents', function (Blueprint $table) {
            $table->dropColumn(['severity', 'category', 'sub_category']);
        });

        Schema::table('incident_investigations', function (Blueprint $table) {
            $table->dropColumn(['corrective_action_owner', 'recurrence_prevention', 'investigation_outcome']);
        });
    }
};
