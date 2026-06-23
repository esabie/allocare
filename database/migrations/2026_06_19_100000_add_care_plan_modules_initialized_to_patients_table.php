<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'care_plan_modules_initialized')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('care_plan_modules_initialized')->default(false)->after('avatar');
        });

        if (Schema::hasTable('patient_care_plan_modules')) {
            DB::table('patients')
                ->whereIn('id', function ($query) {
                    $query->select('patient_id')->from('patient_care_plan_modules');
                })
                ->update(['care_plan_modules_initialized' => true]);
        }

        if (Schema::hasTable('patient_care_plan_forms')) {
            DB::table('patients')
                ->whereIn('url_key', function ($query) {
                    $query->select('patient_slug')->from('patient_care_plan_forms');
                })
                ->update(['care_plan_modules_initialized' => true]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'care_plan_modules_initialized')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('care_plan_modules_initialized');
        });
    }
};
