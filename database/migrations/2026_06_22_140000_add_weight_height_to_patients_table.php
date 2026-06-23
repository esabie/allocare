<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'weight_kg')) {
                $table->decimal('weight_kg', 6, 2)->nullable()->after('staffing_ratio');
            }
            if (! Schema::hasColumn('patients', 'height_m')) {
                $table->decimal('height_m', 4, 2)->nullable()->after('weight_kg');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'height_m')) {
                $table->dropColumn('height_m');
            }
            if (Schema::hasColumn('patients', 'weight_kg')) {
                $table->dropColumn('weight_kg');
            }
        });
    }
};
