<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_care_plan_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('module_slug');
            $table->string('custom_title')->nullable();
            $table->text('purpose')->nullable();
            $table->boolean('is_bespoke')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'module_slug']);
            $table->index(['patient_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_care_plan_modules');
    }
};
