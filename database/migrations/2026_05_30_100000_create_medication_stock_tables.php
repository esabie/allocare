<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medication_id')->constrained('patient_medications')->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('unit', 32)->default('doses');
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('patient_medication_id');
        });

        Schema::create('medication_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medication_id')->constrained('patient_medications')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity_delta', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('reference', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('medication_administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_stock_movements');
        Schema::dropIfExists('medication_stocks');
    }
};
