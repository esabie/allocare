<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('witness_name');
            $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by_user_id');
        });

        Schema::create('emar_weekly_audits', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique('week_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emar_weekly_audits');

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
