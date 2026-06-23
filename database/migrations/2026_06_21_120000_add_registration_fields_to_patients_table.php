<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('care_group')->nullable()->after('staffing_ratio');
            $table->date('service_start_date')->nullable()->after('care_group');
            $table->timestamp('profile_completion_due_at')->nullable()->after('service_start_date');
            $table->timestamp('profile_completed_at')->nullable()->after('profile_completion_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'care_group',
                'service_start_date',
                'profile_completion_due_at',
                'profile_completed_at',
            ]);
        });
    }
};
