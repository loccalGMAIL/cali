<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'invoice_number' => fake()->unique()->numerify('####-########'),
            'total' => 0,
            'notes' => null,
            'user_id' => User::factory()->admin(),
        ];
    }
}
