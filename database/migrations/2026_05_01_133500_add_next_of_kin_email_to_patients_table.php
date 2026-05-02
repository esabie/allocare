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
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'next_of_kin_email')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('next_of_kin_email')->nullable()->after('next_of_kin_tel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'next_of_kin_email')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('next_of_kin_email');
        });
    }
};

