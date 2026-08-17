<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('care_journal_entries', 'shift_type')) {
                $table->string('shift_type', 16)->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('care_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('care_journal_entries', 'shift_type')) {
                $table->dropColumn('shift_type');
            }
        });
    }
};
