<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_care_plan_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('export_reference', 36)->unique();
            $table->string('format', 16);
            $table->string('scope', 32);
            $table->json('plan_slugs');
            $table->json('version_snapshot')->nullable();
            $table->json('external_document_ids')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('exported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_care_plan_exports');
    }
};
