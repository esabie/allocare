<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'lifecycle_status')) {
                $table->string('lifecycle_status', 32)->default('active')->after('status');
            }
        });

        DB::table('patients')
            ->whereNull('lifecycle_status')
            ->update(['lifecycle_status' => 'active']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'lifecycle_status')) {
                $table->dropColumn('lifecycle_status');
            }
        });
    }
};
