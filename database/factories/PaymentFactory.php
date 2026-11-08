<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'date' => today()->toDateString(),
            'amount' => 1000,
            'method' => PaymentMethod::Cash,
            'collected_by' => User::factory(),
            'notes' => null,
        ];
    }

    public function downPayment(): static
    {
        return $this->state(fn () => ['sale_id' => Sale::factory()]);
    }
}
