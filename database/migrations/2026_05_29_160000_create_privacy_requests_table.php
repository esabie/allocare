<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 32);
            $table->string('status', 32)->default('pending');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('subject_name')->nullable();
            $table->string('subject_email')->nullable();
            $table->text('request_details');
            $table->text('outcome_notes')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['request_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
