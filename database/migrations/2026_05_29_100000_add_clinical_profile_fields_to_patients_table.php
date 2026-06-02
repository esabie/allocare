<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('preferred_name')->nullable()->after('name');
            $table->string('gp_name')->nullable()->after('nhs_number');
            $table->string('gp_practice')->nullable()->after('gp_name');
            $table->string('gp_phone')->nullable()->after('gp_practice');
            $table->string('primary_language')->nullable()->after('gp_phone');
            $table->boolean('interpreter_required')->default(false)->after('primary_language');
            $table->string('capacity_status')->nullable()->after('interpreter_required');
            $table->text('best_interest_decision')->nullable()->after('capacity_status');
            $table->string('information_sharing_consent')->nullable()->after('best_interest_decision');
            $table->string('dols_lps_status')->nullable()->after('information_sharing_consent');
            $table->string('dnacpr_status')->nullable()->after('dols_lps_status');
            $table->json('allergy_details')->nullable()->after('allergies');
            $table->string('mobility_aids')->nullable()->after('dnacpr_status');
            $table->string('hoist_type')->nullable()->after('mobility_aids');
            $table->string('sling_size')->nullable()->after('hoist_type');
            $table->text('equipment_notes')->nullable()->after('sling_size');
            $table->text('environmental_notes')->nullable()->after('equipment_notes');
            $table->string('social_worker_name')->nullable()->after('social_services_number');
            $table->string('social_worker_contact')->nullable()->after('social_worker_name');
            $table->string('commissioner_name')->nullable()->after('social_worker_contact');
            $table->string('commissioner_contact')->nullable()->after('commissioner_name');
            $table->string('emergency_contact_name')->nullable()->after('commissioner_contact');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('primary_diagnosis')->nullable()->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_name',
                'gp_name',
                'gp_practice',
                'gp_phone',
                'primary_language',
                'interpreter_required',
                'capacity_status',
                'best_interest_decision',
                'information_sharing_consent',
                'dols_lps_status',
                'dnacpr_status',
                'allergy_details',
                'mobility_aids',
                'hoist_type',
                'sling_size',
                'equipment_notes',
                'environmental_notes',
                'social_worker_name',
                'social_worker_contact',
                'commissioner_name',
                'commissioner_contact',
                'emergency_contact_name',
                'emergency_contact_phone',
                'primary_diagnosis',
            ]);
        });
    }
};
