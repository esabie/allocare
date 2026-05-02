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
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'staffing_ratio')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('staffing_ratio')->nullable()->after('rag_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'staffing_ratio')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('staffing_ratio');
        });
    }
};

