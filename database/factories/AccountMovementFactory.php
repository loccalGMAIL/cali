<?php

namespace Database\Factories;

use App\Enums\AccountMovementType;
use App\Models\AccountMovement;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountMovement>
 */
class AccountMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'date' => today()->toDateString(),
            'type' => AccountMovementType::Adjustment,
            'description' => 'Ajuste',
            'debit' => 0,
            'credit' => 0,
            'balance' => 0,
        ];
    }
}
