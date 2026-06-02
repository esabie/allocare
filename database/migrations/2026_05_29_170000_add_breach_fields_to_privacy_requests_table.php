<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->dateTime('discovered_at')->nullable()->after('request_details');
            $table->boolean('ico_notification_required')->default(false)->after('discovered_at');
            $table->dateTime('ico_notified_at')->nullable()->after('ico_notification_required');
            $table->unsignedInteger('individuals_affected_count')->nullable()->after('ico_notified_at');
            $table->string('breach_categories', 255)->nullable()->after('individuals_affected_count');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->dropColumn([
                'discovered_at',
                'ico_notification_required',
                'ico_notified_at',
                'individuals_affected_count',
                'breach_categories',
            ]);
        });
    }
};
