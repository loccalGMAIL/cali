<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Services\SaleCalculator;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateSale
{
    public function __construct(
        private readonly SaleCalculator $calculator,
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
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La venta necesita al menos un producto.']);
        }

        $this->ensureItemsAreValid($items);

        return DB::transaction(function () use ($data, $items, $user): Sale {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $plan = FinancingPlan::query()->findOrFail($data['financing_plan_id']);

            $lines = array_map(fn (array $item): array => [
                'quantity' => (int) $item['quantity'],
                'unit_cost' => $this->money($item['unit_cost']),
                'unit_freight' => $this->money($item['unit_freight'] ?? 0),
                'unit_margin' => $this->money($item['unit_margin']),
                'unit_price' => $this->calculator->unitPrice($item['unit_cost'], $item['unit_margin'], $item['unit_freight'] ?? 0),
            ], $items);

            try {
                $result = $this->calculator->calculate(
                    $lines,
                    $data['down_payment'] ?? 0,
                    $plan->installments_count,
                    $data['first_due_date'],
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['down_payment' => $exception->getMessage()]);
            }

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

            foreach ($items as $index => $item) {
                $line = $lines[$index];

                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'unit_freight' => $line['unit_freight'],
                    'unit_margin' => $line['unit_margin'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => bcmul((string) $line['quantity'], $line['unit_price'], 2),
                    'pending_quantity' => $line['quantity'],
                ]);
            }

            foreach ($result['installments'] as $installment) {
                $sale->installments()->create([
                    'customer_id' => $customer->getKey(),
                    'number' => $installment['number'],
                    'due_date' => $installment['due_date'],
                    'amount' => $installment['amount'],
                    'cost_portion' => $installment['cost_portion'],
                    'paid_amount' => '0.00',
                    'status' => InstallmentStatus::Pending,
                ]);
            }

            $this->accounts->record(
                $customer,
                AccountMovementType::Sale,
                "Venta #{$sale->getKey()}",
                debit: $result['total'],
                reference: $sale,
                user: $user,
                date: $data['date'],
            );

            if (bccomp($result['down_payment'], '0', 2) > 0) {
                $this->registerDownPayment($sale, $customer, $result['down_payment'], $data, $user);
            }

            $this->activity->log(
                ActivityEvent::Created,
                "Registró la venta #{$sale->getKey()} a {$customer->name} por ".Money::format($sale->total)." en {$sale->installments_count} cuota".($sale->installments_count === 1 ? '' : 's'),
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

    /**
     * Los productos tienen que existir y estar activos; cantidad, costo, flete y margen no pueden ser negativos.
     *
     * @param  list<array{product_id: int, quantity: int, unit_cost: string|int|float, unit_margin: string|int|float, unit_freight?: string|int|float|null}>  $items
     *
     * @throws ValidationException
     */
    private function ensureItemsAreValid(array $items): void
    {
        $productIds = array_unique(array_map(fn (array $item): int => (int) $item['product_id'], $items));
        $activeProducts = Product::query()->active()->whereKey($productIds)->count();

        if ($activeProducts !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'Uno de los productos no existe o está inactivo.']);
        }

        foreach ($items as $item) {
            if ((int) $item['quantity'] < 1) {
                throw ValidationException::withMessages(['items' => 'La cantidad de cada producto debe ser al menos 1.']);
            }

            if ((float) $item['unit_cost'] < 0 || (float) $item['unit_margin'] < 0 || (float) ($item['unit_freight'] ?? 0) < 0) {
                throw ValidationException::withMessages(['items' => 'El costo, el flete y el margen no pueden ser negativos.']);
            }
        }
    }

    /**
     * El anticipo se registra como cobro (con su crédito en la cuenta corriente).
     *
     * @param  array<string, mixed>  $data
     */
    private function registerDownPayment(Sale $sale, Customer $customer, string $amount, array $data, User $user): void
    {
        $method = $data['down_payment_method'] ?? PaymentMethod::Cash;

        $payment = Payment::create([
            'customer_id' => $customer->getKey(),
            'sale_id' => $sale->getKey(),
            'date' => $data['date'],
            'amount' => $amount,
            'method' => $method instanceof PaymentMethod ? $method : PaymentMethod::from((string) $method),
            'collected_by' => $user->getKey(),
            'notes' => "Anticipo de la venta #{$sale->getKey()}",
        ]);

        $this->accounts->record(
            $customer,
            AccountMovementType::Payment,
            "Anticipo venta #{$sale->getKey()}",
            credit: $amount,
            reference: $payment,
            user: $user,
            date: $data['date'],
        );
    }

    private function money(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
