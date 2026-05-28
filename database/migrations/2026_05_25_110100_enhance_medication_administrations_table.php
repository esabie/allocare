<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('notes');
            $table->foreignId('witness_user_id')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->string('witness_name')->nullable()->after('witness_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['witness_user_id']);
            $table->dropColumn(['reason', 'witness_user_id', 'witness_name']);
        });
    }
};
