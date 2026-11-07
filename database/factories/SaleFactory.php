<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea la fila de la venta tal cual, sin cuotas ni stock. Para una venta completa
 * (con cuotas, stock y cuenta corriente) usar la acción CreateSale.
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
            'surcharge_percent' => 15,
            'subtotal' => 1000,
            'down_payment' => 0,
            'financed_amount' => 1150,
            'total' => 1150,
            'total_cost' => 600,
            'first_due_date' => today()->addMonth()->toDateString(),
            'status' => SaleStatus::Active,
            'notes' => null,
        ];
    }
}
