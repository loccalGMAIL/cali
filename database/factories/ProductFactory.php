<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'description' => null,
            'active' => true,
            'last_cost' => fake()->numberBetween(50000, 800000),
            'freight' => 0,
        ];
    }
}
