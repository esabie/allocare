<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->string('frequency')->nullable()->after('is_prn');
            $table->json('scheduled_times')->nullable()->after('frequency');
            $table->date('start_date')->nullable()->after('scheduled_times');
            $table->date('end_date')->nullable()->after('start_date');
            $table->boolean('is_controlled')->default(false)->after('end_date');
            $table->string('prn_indication')->nullable()->after('is_controlled');
            $table->integer('prn_max_daily_doses')->nullable()->after('prn_indication');
        });
    }

    public function down(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->dropColumn([
                'frequency',
                'scheduled_times',
                'start_date',
                'end_date',
                'is_controlled',
                'prn_indication',
                'prn_max_daily_doses',
            ]);
        });
    }
};
