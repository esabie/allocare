<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('data_category', 128);
            $table->string('retention_period', 128);
            $table->string('legal_basis', 255)->nullable();
            $table->unsignedSmallInteger('review_cycle_months')->default(12);
            $table->date('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('privacy_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('version', 32);
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->date('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_notices');
        Schema::dropIfExists('data_retention_schedules');
    }
};
