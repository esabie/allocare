<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'must_reset_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_reset_password')->default(false)->after('password');
            $table->index('must_reset_password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'must_reset_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['must_reset_password']);
            $table->dropColumn('must_reset_password');
        });
    }
};
