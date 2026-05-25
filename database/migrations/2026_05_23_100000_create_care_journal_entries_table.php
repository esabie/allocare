<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('care_journal_entries')) {
            return;
        }

        Schema::create('care_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['recorded_at', 'id']);
            $table->index(['patient_id', 'recorded_at']);
            $table->index(['author_user_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_journal_entries');
    }
};
