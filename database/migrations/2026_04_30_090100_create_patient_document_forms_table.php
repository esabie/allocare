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
        Schema::create('patient_document_forms', function (Blueprint $table) {
            $table->id();
            $table->string('patient_slug');
            $table->string('document_slug');
            $table->json('data')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_slug', 'document_slug']);
            $table->index(['patient_slug', 'document_slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_document_forms');
    }
};

