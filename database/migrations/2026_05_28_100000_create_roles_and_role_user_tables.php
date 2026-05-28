<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });

        $now = now();
        $roles = [
            ['name' => 'super_admin', 'description' => 'Full platform administration'],
            ['name' => 'admin', 'description' => 'Operational administration'],
            ['name' => 'care_manager', 'description' => 'Care planning and oversight'],
            ['name' => 'supervisor', 'description' => 'Workforce supervision'],
            ['name' => 'care_worker', 'description' => 'Direct care delivery'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'name' => $role['name'],
                'description' => $role['description'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasColumn('users', 'primary_role')) {
            $roleIdByName = DB::table('roles')->pluck('id', 'name');
            $users = DB::table('users')
                ->select('id', 'primary_role')
                ->whereNotNull('primary_role')
                ->get();

            foreach ($users as $user) {
                $normalizedRole = strtolower(trim((string) $user->primary_role));
                $roleId = $roleIdByName[$normalizedRole] ?? null;
                if ($roleId === null) {
                    continue;
                }

                DB::table('role_user')->insert([
                    'role_id' => $roleId,
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
