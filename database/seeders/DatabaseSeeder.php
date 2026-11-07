<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FinancingPlanSeeder::class);

        User::updateOrCreate(
            ['email' => config('cali.admin.email')],
            [
                'name' => config('cali.admin.name'),
                'password' => config('cali.admin.password'),
                'role' => UserRole::Admin,
                'active' => true,
            ],
        );
    }
}
