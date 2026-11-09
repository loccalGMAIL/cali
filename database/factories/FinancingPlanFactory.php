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
            'active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'name' => 'Contado',
            'installments_count' => 1,
        ]);
    }
}
