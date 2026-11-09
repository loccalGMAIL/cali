<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea la fila de la venta tal cual, sin ítems ni cuotas. Para una venta completa
 * (con ítems, cuotas y cuenta corriente) usar la acción CreateSale.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'seller_id' => User::factory()->seller(),
            'date' => today()->toDateString(),
            'financing_plan_id' => FinancingPlan::factory(),
            'installments_count' => 3,
            'total' => 1000,
            'total_cost' => 600,
            'down_payment' => 0,
            'financed_amount' => 1000,
            'first_due_date' => today()->addMonth()->toDateString(),
            'status' => SaleStatus::Active,
            'notes' => null,
        ];
    }
}
