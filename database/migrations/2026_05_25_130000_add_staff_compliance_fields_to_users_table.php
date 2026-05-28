<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('postcode');
            $table->string('dbs_certificate_number')->nullable()->after('mfa_enabled');
            $table->date('dbs_issue_date')->nullable()->after('dbs_certificate_number');
            $table->date('dbs_expiry_date')->nullable()->after('dbs_issue_date');
            $table->string('dbs_status')->nullable()->after('dbs_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'dbs_certificate_number',
                'dbs_issue_date',
                'dbs_expiry_date',
                'dbs_status',
            ]);
        });
    }
};
