<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('session_id', 120)->nullable()->after('user_agent');
            $table->string('device_type', 32)->nullable()->after('session_id');
            $table->json('previous_values')->nullable()->after('changes');
            $table->json('new_values')->nullable()->after('previous_values');
            $table->string('integrity_hash', 64)->nullable()->after('metadata');

            $table->index('session_id');
            $table->index('action');
        });

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->string('session_id', 120)->nullable()->after('user_agent');
            $table->string('device_type', 32)->nullable()->after('session_id');

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['action']);
            $table->dropColumn(['session_id', 'device_type', 'previous_values', 'new_values', 'integrity_hash']);
        });

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropColumn(['session_id', 'device_type']);
        });
    }
};
