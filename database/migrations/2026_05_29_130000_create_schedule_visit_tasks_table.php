<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_visit_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_schedule_id')->constrained('patient_schedules')->cascadeOnDelete();
            $table->string('task_key', 64);
            $table->string('task_label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('outcome', 32)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_schedule_id', 'task_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_visit_tasks');
    }
};
