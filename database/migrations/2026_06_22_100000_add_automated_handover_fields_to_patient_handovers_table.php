<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_handovers', function (Blueprint $table) {
            $table->boolean('auto_generated')->default(false)->after('recorded_at');
            $table->json('auto_snapshot')->nullable()->after('auto_generated');
            $table->timestamp('period_start_at')->nullable()->after('auto_snapshot');
            $table->timestamp('period_end_at')->nullable()->after('period_start_at');
            $table->foreignId('acknowledged_by_user_id')->nullable()->after('period_end_at')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_handovers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by_user_id');
            $table->dropColumn([
                'auto_generated',
                'auto_snapshot',
                'period_start_at',
                'period_end_at',
                'acknowledged_at',
            ]);
        });
    }
};
