<?php

namespace Database\Factories;

use App\Models\FinancingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingPlan>
 */
class FinancingPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $installments = fake()->numberBetween(1, 8);

        return [
            'name' => $installments.' cuotas',
            'installments_count' => $installments,
            'surcharge_percent' => $installments * 5,
            'active' => true,
            'sort_order' => $installments,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'name' => 'Contado',
            'installments_count' => 1,
            'surcharge_percent' => 0,
            'sort_order' => 0,
        ]);
    }
}
