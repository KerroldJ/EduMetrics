<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(RolePermissionSeeder::class);

        $accounts = [
            ['name' => 'EduMetric Admin', 'email' => 'admin@edumetric.test', 'role' => 'Admin'],
            ['name' => 'EduMetric Teacher', 'email' => 'teacher@edumetric.test', 'role' => 'Teacher'],
            ['name' => 'EduMetric Student', 'email' => 'student@edumetric.test', 'role' => 'Student'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => 'password',
                ],
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
