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
        if (! Schema::hasTable('patient_vitals')) {
            return;
        }

        Schema::table('patient_vitals', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_vitals', 'other_observation')) {
                $table->text('other_observation')->nullable()->after('spo2');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('patient_vitals')) {
            return;
        }

        Schema::table('patient_vitals', function (Blueprint $table) {
            if (Schema::hasColumn('patient_vitals', 'other_observation')) {
                $table->dropColumn('other_observation');
            }
        });
    }
};
