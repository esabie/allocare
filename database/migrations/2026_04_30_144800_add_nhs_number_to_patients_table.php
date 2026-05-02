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
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'nhs_number')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('nhs_number')->nullable()->unique()->after('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'nhs_number')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('nhs_number');
        });
    }
};

