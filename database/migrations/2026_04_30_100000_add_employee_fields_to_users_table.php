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
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('password');
            $table->string('first_name')->nullable()->after('title');
            $table->string('surname')->nullable()->after('first_name');
            $table->string('date_of_birth')->nullable()->after('surname');
            $table->string('sex')->nullable()->after('date_of_birth');
            $table->string('username')->nullable()->unique()->after('sex');
            $table->string('home_address')->nullable()->after('username');
            $table->string('city')->nullable()->after('home_address');
            $table->string('postcode')->nullable()->after('city');
            $table->string('primary_role')->nullable()->after('postcode');
            $table->boolean('mfa_enabled')->default(true)->after('primary_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'title',
                'first_name',
                'surname',
                'date_of_birth',
                'sex',
                'username',
                'home_address',
                'city',
                'postcode',
                'primary_role',
                'mfa_enabled',
            ]);
        });
    }
};

