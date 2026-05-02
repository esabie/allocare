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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'photo_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('primary_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'photo_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};

