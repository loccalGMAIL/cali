<?php

namespace Database\Factories;

use App\Enums\InstallmentStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Sale;
use App\Services\SaleCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'customer_id' => Customer::factory(),
            'number' => 1,
            'due_date' => SaleCalculator::dueDateFor(today(), 1)->toDateString(),
            'amount' => 1000,
            'paid_amount' => 0,
            'cost_portion' => 600,
            'status' => InstallmentStatus::Pending,
        ];
    }

    public function overdue(int $days = 10): static
    {
        return $this->state(fn () => ['due_date' => today()->subDays($days)->toDateString()]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['amount'],
            'status' => InstallmentStatus::Paid,
        ]);
    }
}
