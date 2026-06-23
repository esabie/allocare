<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->string('generic_name', 255)->nullable()->after('name');
            $table->string('brand_name', 255)->nullable()->after('generic_name');
            $table->string('dose_amount', 32)->nullable()->after('dose');
            $table->string('dose_unit', 32)->nullable()->after('dose_amount');
            $table->string('prescriber_name', 255)->nullable()->after('end_date');
            $table->string('prescriber_contact', 255)->nullable()->after('prescriber_name');
            $table->boolean('is_time_critical')->default(false)->after('is_controlled');
            $table->boolean('is_ongoing')->default(true)->after('end_date');
            $table->text('special_instructions')->nullable()->after('prn_max_daily_doses');
            $table->unsignedSmallInteger('prn_min_interval_minutes')->nullable()->after('prn_max_daily_doses');
        });
    }

    public function down(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->dropColumn([
                'generic_name',
                'brand_name',
                'dose_amount',
                'dose_unit',
                'prescriber_name',
                'prescriber_contact',
                'is_time_critical',
                'is_ongoing',
                'special_instructions',
                'prn_min_interval_minutes',
            ]);
        });
    }
};
