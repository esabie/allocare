<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_schedules', function (Blueprint $table) {
            $table->dateTime('checked_in_at')->nullable()->after('end_at');
            $table->dateTime('checked_out_at')->nullable()->after('checked_in_at');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('checked_out_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->unsignedInteger('check_in_distance_metres')->nullable()->after('check_out_longitude');
            $table->unsignedInteger('check_out_distance_metres')->nullable()->after('check_in_distance_metres');
            $table->integer('late_by_minutes')->nullable()->after('check_out_distance_metres');
            $table->integer('left_early_by_minutes')->nullable()->after('late_by_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('patient_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'checked_in_at',
                'checked_out_at',
                'check_in_latitude',
                'check_in_longitude',
                'check_out_latitude',
                'check_out_longitude',
                'check_in_distance_metres',
                'check_out_distance_metres',
                'late_by_minutes',
                'left_early_by_minutes',
            ]);
        });
    }
};
