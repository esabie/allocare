<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_wound_assessments', function (Blueprint $table) {
            $table->string('body_map_region', 64)->nullable()->after('body_map_notes');
            $table->string('photo_path')->nullable()->after('body_map_region');
            $table->date('review_due_at')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('patient_wound_assessments', function (Blueprint $table) {
            $table->dropColumn(['body_map_region', 'photo_path', 'review_due_at']);
        });
    }
};
