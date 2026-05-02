<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'account_status')) {
            return;
        }

        DB::table('users')
            ->whereNull('account_status')
            ->update(['account_status' => 'active']);

        DB::table('users')
            ->whereNotIn('account_status', ['active', 'inactive'])
            ->update(['account_status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op: normalization is data-corrective and irreversible by design.
    }
};

