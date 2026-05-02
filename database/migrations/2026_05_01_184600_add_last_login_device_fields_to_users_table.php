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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_login_os')) {
                $table->string('last_login_os')->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'last_login_app_version')) {
                $table->string('last_login_app_version')->nullable()->after('last_login_os');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_login_app_version')) {
                $table->dropColumn('last_login_app_version');
            }

            if (Schema::hasColumn('users', 'last_login_os')) {
                $table->dropColumn('last_login_os');
            }
        });
    }
};
