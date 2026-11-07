<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\SaleCalculator;
use App\Services\StockService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateSale
{
    public function __construct(
        private readonly SaleCalculator $calculator,
        private readonly AccountService $accounts,
        private readonly StockService $stock,
    ) {}

    /**
     * Confirma una venta: valida y descuenta stock, congela el plan, genera las cuotas,
     * registra el anticipo como cobro y deja los movimientos en la cuenta corriente.
     * Todo ocurre en una única transacción.
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
     * @param  list<array{product_id: int, quantity: int, unit_price: string|int|float}>  $items
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $items, User $user): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La venta necesita al menos un producto.']);
        }

        return DB::transaction(function () use ($data, $items, $user): Sale {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $plan = FinancingPlan::query()->findOrFail($data['financing_plan_id']);

            $products = $this->lockAndCheckStock($items);

            $lines = array_map(fn (array $item): array => [
                'quantity' => (int) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'unit_cost' => $products[$item['product_id']]->last_cost,
            ], $items);

            try {
                $result = $this->calculator->calculate(
                    $lines,
                    $data['down_payment'] ?? 0,
                    $plan->surcharge_percent,
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
                'surcharge_percent' => $plan->surcharge_percent,
                'subtotal' => $result['subtotal'],
                'down_payment' => $result['down_payment'],
                'financed_amount' => $result['financed_amount'],
                'total' => $result['total'],
                'total_cost' => $result['total_cost'],
                'first_due_date' => $data['first_due_date'],
                'status' => $result['installments'] === [] ? SaleStatus::Paid : SaleStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $lines[$index]['quantity'],
                    'unit_price' => $this->money($item['unit_price']),
                    'unit_cost' => $this->money($lines[$index]['unit_cost']),
                    'subtotal' => bcmul((string) $lines[$index]['quantity'], $this->money($item['unit_price']), 2),
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

            foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
                $this->stock->record($products[$productId], StockMovementType::Sale, -$quantity, $sale, $user);
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

            return $sale->load(['items', 'installments']);
        });
    }

    /**
     * Bloquea los productos (en orden de id, para evitar deadlocks) y verifica que alcance el stock.
     *
     * @param  list<array{product_id: int, quantity: int, unit_price: string|int|float}>  $items
     * @return array<int, Product>
     */
    private function lockAndCheckStock(array $items): array
    {
        $quantities = $this->quantitiesByProduct($items);

        $products = Product::query()
            ->whereIn('id', array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();

        $errors = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products[$productId] ?? null;

            if ($product === null) {
                $errors['items'] = 'Uno de los productos ya no existe.';

                continue;
            }

            if ($product->stock < $quantity) {
                $errors['items'] = "Stock insuficiente para «{$product->name}»: hay {$product->stock} y se piden {$quantity}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $products;
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_price: string|int|float}>  $items
     * @return array<int, int>
     */
    private function quantitiesByProduct(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[$item['product_id']] = ($quantities[$item['product_id']] ?? 0) + (int) $item['quantity'];
        }

        ksort($quantities);

        return $quantities;
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

    private function money(string|int|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
