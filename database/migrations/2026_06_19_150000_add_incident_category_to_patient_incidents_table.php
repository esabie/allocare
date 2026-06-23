<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_incidents', function (Blueprint $table) {
            $table->string('incident_category', 64)->nullable()->after('incident_title');
        });
    }

    public function down(): void
    {
        Schema::table('patient_incidents', function (Blueprint $table) {
            $table->dropColumn('incident_category');
        });
    }
};
