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
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'photo_path')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('nhs_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'photo_path')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};

