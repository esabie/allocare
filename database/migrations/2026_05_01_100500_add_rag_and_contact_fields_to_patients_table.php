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
        if (!Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'rag_status')) {
                $table->string('rag_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('patients', 'next_of_kin')) {
                $table->string('next_of_kin')->nullable()->after('rag_status');
            }
            if (!Schema::hasColumn('patients', 'next_of_kin_tel')) {
                $table->string('next_of_kin_tel')->nullable()->after('next_of_kin');
            }
            if (!Schema::hasColumn('patients', 'other_relevant_people')) {
                $table->text('other_relevant_people')->nullable()->after('next_of_kin_tel');
            }
            if (!Schema::hasColumn('patients', 'social_services_number')) {
                $table->string('social_services_number')->nullable()->after('other_relevant_people');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'social_services_number')) {
                $table->dropColumn('social_services_number');
            }
            if (Schema::hasColumn('patients', 'other_relevant_people')) {
                $table->dropColumn('other_relevant_people');
            }
            if (Schema::hasColumn('patients', 'next_of_kin_tel')) {
                $table->dropColumn('next_of_kin_tel');
            }
            if (Schema::hasColumn('patients', 'next_of_kin')) {
                $table->dropColumn('next_of_kin');
            }
            if (Schema::hasColumn('patients', 'rag_status')) {
                $table->dropColumn('rag_status');
            }
        });
    }
};

