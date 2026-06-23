<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        Patient::query()->each(function (Patient $patient): void {
            $patient->syncRagStatus($patient->rag_status ?? $patient->status ?? 'green');
            $patient->saveQuietly();
        });
    }

    public function down(): void
    {
        // Non-reversible data normalisation.
    }
};
