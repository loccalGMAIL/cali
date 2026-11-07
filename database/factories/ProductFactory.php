<?php

namespace Database\Factories;

use App\Enums\PriceMode;
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
            'stock' => fake()->numberBetween(0, 30),
            'min_stock' => 2,
            'last_cost' => fake()->numberBetween(50000, 800000),
            'margin_percent' => 30,
            'price_mode' => PriceMode::Auto,
            'sale_price' => 0,
        ];
    }

    public function manualPrice(float $price): static
    {
        return $this->state(fn () => [
            'price_mode' => PriceMode::Manual,
            'sale_price' => $price,
        ]);
    }
}
