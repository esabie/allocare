<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_erasure_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('privacy_request_id')->constrained('privacy_requests')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('privacy_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_erasure_jobs');
    }
};
