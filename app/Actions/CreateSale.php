<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Services\SaleCalculator;
use App\Services\SaleRecorder;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function __construct(
        private readonly SaleRecorder $recorder,
        private readonly AccountService $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Confirma una venta: congela el plan, genera las cuotas, registra el anticipo como cobro,
     * deja los movimientos en la cuenta corriente y marca lo vendido como pendiente de compra
     * (no hay stock: todo se le pide después al proveedor). Todo ocurre en una única transacción.
     *
     * Cada ítem vale costo + flete + margen, cargados al vender. El flete es opcional (si no viene
     * es 0) y se divide en las cuotas junto con el resto.
     *
     * @param  array{
     *     customer_id: int,
     *     seller_id: int,
     *     financing_plan_id: int,
     *     date: CarbonInterface|string,
     *     first_due_date: CarbonInterface|string,
     *     down_payment?: string|int|float|null,
     *     down_payment_method?: PaymentMethod|string|null,
     *     notes?: string|null
     * }  $data
     * @param  list<array{product_id: int, quantity: int, unit_cost: string|int|float, unit_margin: string|int|float, unit_freight?: string|int|float|null}>  $items
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $items, User $user): Sale
    {
        $this->recorder->ensureItemsAreValid($items);

        return DB::transaction(function () use ($data, $items, $user): Sale {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $plan = FinancingPlan::query()->findOrFail($data['financing_plan_id']);

            ['lines' => $lines, 'result' => $result] = $this->recorder->calculate(
                $items,
                $data['down_payment'] ?? 0,
                $plan->installments_count,
                $data['first_due_date'],
            );

            $sale = Sale::create([
                'customer_id' => $customer->getKey(),
                'seller_id' => $data['seller_id'],
                'date' => $data['date'],
                'financing_plan_id' => $plan->getKey(),
                'installments_count' => $plan->installments_count,
                'total' => $result['total'],
                'total_cost' => $result['total_cost'],
                'total_freight' => $result['total_freight'],
                'down_payment' => $result['down_payment'],
                'financed_amount' => $result['financed_amount'],
                'first_due_date' => SaleCalculator::dueDateFor($data['first_due_date'])->toDateString(),
                'status' => $result['installments'] === [] ? SaleStatus::Paid : SaleStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recorder->recordDetails($sale, $items, $lines, $result);

            $this->accounts->record(
                $customer,
                AccountMovementType::Sale,
                "Venta {$sale->number()}",
                debit: $result['total'],
                reference: $sale,
                user: $user,
                date: $data['date'],
            );

            if (bccomp($result['down_payment'], '0', 2) > 0) {
                $this->recorder->registerDownPayment($sale, $result['down_payment'], $data['down_payment_method'] ?? null, $data['date'], $user);
            }

            $this->activity->log(
                ActivityEvent::Created,
                "Registró la venta {$sale->number()} a {$customer->name} por ".Money::format($sale->total)." en {$sale->installments_count} cuota".($sale->installments_count === 1 ? '' : 's'),
                $sale,
                [
                    'total' => $sale->total,
                    'total_cost' => $sale->total_cost,
                    'total_freight' => $sale->total_freight,
                    'down_payment' => $sale->down_payment,
                    'installments_count' => $sale->installments_count,
                    'seller_id' => $sale->seller_id,
                ],
                $user,
            );

            return $sale->load(['items', 'installments']);
        });
    }
}
