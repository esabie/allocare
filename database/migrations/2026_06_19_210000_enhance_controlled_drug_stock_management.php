<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_stock_movements', function (Blueprint $table) {
            $table->foreignId('witness_user_id')->nullable()->after('recorded_by_user_id')->constrained('users')->nullOnDelete();
            $table->decimal('expected_balance', 10, 2)->nullable()->after('balance_after');
            $table->decimal('counted_balance', 10, 2)->nullable()->after('expected_balance');
            $table->foreignId('patient_handover_id')->nullable()->after('medication_administration_id')->constrained('patient_handovers')->nullOnDelete();
            $table->boolean('is_permanent_record')->default(false)->after('notes');
        });

        Schema::table('patient_handovers', function (Blueprint $table) {
            $table->boolean('controlled_drug_reconciliation_complete')->default(false)->after('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('patient_handovers', function (Blueprint $table) {
            $table->dropColumn('controlled_drug_reconciliation_complete');
        });

        Schema::table('medication_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['witness_user_id']);
            $table->dropForeign(['patient_handover_id']);
            $table->dropColumn([
                'witness_user_id',
                'expected_balance',
                'counted_balance',
                'patient_handover_id',
                'is_permanent_record',
            ]);
        });
    }
};
