<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'date' => today()->toDateString(),
            'status' => PurchaseOrderStatus::Draft,
            'notes' => null,
            'user_id' => User::factory()->admin(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
