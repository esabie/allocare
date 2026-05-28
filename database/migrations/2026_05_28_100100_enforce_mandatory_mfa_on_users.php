<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('mfa_enabled')
                    ->orWhere('mfa_enabled', false);
            })
            ->update(['mfa_enabled' => true]);
    }

    public function down(): void
    {
        // Intentionally no rollback for security hardening.
    }
};
