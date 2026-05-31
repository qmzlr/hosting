<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Student',
                'password' => Hash::make('student123'),
                'role' => 'user',
                'level' => 'Начинающий',
                'lastSignInAt' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'Teacher',
                'password' => Hash::make('teacher123'),
                'role' => 'teacher',
                'teacher_status' => 'одобрен',
                'lastSignInAt' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'moderator@example.com'],
            [
                'name' => 'Moderator',
                'password' => Hash::make('secret123'),
                'role' => 'moderator',
                'lastSignInAt' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'lastSignInAt' => now(),
            ],
        );

        $this->call(PlatformSeeder::class);
    }
}
