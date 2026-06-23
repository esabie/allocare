<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('care_journal_entries')) {
            return;
        }

        Schema::table('care_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('care_journal_entries', 'amended_by_user_id')) {
                $table->foreignId('amended_by_user_id')
                    ->nullable()
                    ->after('author_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('care_journal_entries')) {
            return;
        }

        Schema::table('care_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('care_journal_entries', 'amended_by_user_id')) {
                $table->dropConstrainedForeignId('amended_by_user_id');
            }
        });
    }
};
