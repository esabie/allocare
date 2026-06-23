<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dateTime('rescheduled_for')->nullable()->after('scheduled_for');
            $table->boolean('is_prn_dose')->default(false)->after('source_mar_slug');
            $table->string('prn_indication')->nullable()->after('is_prn_dose');
            $table->string('effectiveness_rating')->nullable()->after('prn_indication');
            $table->dateTime('next_permissible_dose_at')->nullable()->after('effectiveness_rating');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropColumn([
                'rescheduled_for',
                'is_prn_dose',
                'prn_indication',
                'effectiveness_rating',
                'next_permissible_dose_at',
            ]);
        });
    }
};
