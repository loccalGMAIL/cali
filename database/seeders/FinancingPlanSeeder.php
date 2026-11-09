<?php

namespace Database\Seeders;

use App\Models\FinancingPlan;
use Illuminate\Database\Seeder;

class FinancingPlanSeeder extends Seeder
{
    /**
     * Planes iniciales de ejemplo; el cliente los ajusta desde el panel.
     */
    public function run(): void
    {
        $plans = [
            ['name' => 'Contado', 'installments_count' => 1],
            ['name' => '3 cuotas', 'installments_count' => 3],
            ['name' => '6 cuotas', 'installments_count' => 6],
            ['name' => '8 cuotas', 'installments_count' => 8],
        ];

        foreach ($plans as $plan) {
            FinancingPlan::updateOrCreate(['name' => $plan['name']], $plan + ['active' => true]);
        }
    }
}
