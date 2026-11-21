<?php

namespace App\Services;

use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Lo que tienen en común cargar y editar una venta: validar los ítems, calcular montos y cuotas,
 * guardar el detalle (ítems y cuotas) y registrar el anticipo como cobro. Así la venta editada
 * queda exactamente igual que si se hubiera cargado de nuevo con esos datos.
 *
 * @phpstan-type ItemInput array{product_id: int|string, quantity: int|string, unit_cost: string|int|float, unit_margin: string|int|float, unit_freight?: string|int|float|null}
 * @phpstan-type Line array{quantity: int, unit_cost: string, unit_freight: string, unit_margin: string, unit_price: string}
 *
 * @phpstan-import-type Result from SaleCalculator
 */
class SaleRecorder
{
    public function __construct(
        private readonly SaleCalculator $calculator,
        private readonly AccountService $accounts,
    ) {}

    /**
     * Tiene que haber al menos un ítem; los productos tienen que existir y estar activos (salvo los
     * de `$allowedInactiveProductIds`, que la venta ya tenía); cantidad, costo, flete y margen no
     * pueden ser negativos.
     *
     * @param  list<ItemInput>  $items
     * @param  list<int>  $allowedInactiveProductIds
     *
     * @throws ValidationException
     */
    public function ensureItemsAreValid(array $items, array $allowedInactiveProductIds = []): void
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La venta necesita al menos un producto.']);
        }

        $productIds = array_values(array_unique(array_map(fn (array $item): int => (int) $item['product_id'], $items)));
        $availableProducts = Product::query()
            ->whereKey($productIds)
            ->where(fn ($query) => $query->active()->orWhereIn('id', $allowedInactiveProductIds))
            ->count();

        if ($availableProducts !== count($productIds)) {
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
     * Normaliza los ítems (cada uno vale costo + flete + margen) y calcula totales y cuotas.
     *
     * @param  list<ItemInput>  $items
     * @return array{lines: list<Line>, result: Result}
     *
     * @throws ValidationException
     */
    public function calculate(array $items, string|int|float|null $downPayment, int $installmentsCount, CarbonInterface|string $firstDueDate): array
    {
        $lines = array_map(fn (array $item): array => [
            'quantity' => (int) $item['quantity'],
            'unit_cost' => $this->money($item['unit_cost']),
            'unit_freight' => $this->money($item['unit_freight'] ?? 0),
            'unit_margin' => $this->money($item['unit_margin']),
            'unit_price' => $this->calculator->unitPrice($item['unit_cost'], $item['unit_margin'], $item['unit_freight'] ?? 0),
        ], $items);

        try {
            $result = $this->calculator->calculate($lines, $downPayment ?? 0, $installmentsCount, $firstDueDate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['down_payment' => $exception->getMessage()]);
        }

        return ['lines' => $lines, 'result' => $result];
    }

    /**
     * Guarda los ítems vendidos (todo queda pendiente de compra) y las cuotas de la venta.
     *
     * @param  list<ItemInput>  $items
     * @param  list<Line>  $lines
     * @param  Result  $result
     */
    public function recordDetails(Sale $sale, array $items, array $lines, array $result): void
    {
        foreach ($items as $index => $item) {
            $sale->items()->create($this->itemAttributes($item, $lines[$index]));
        }

        foreach ($result['installments'] as $installment) {
            $sale->installments()->create($this->installmentAttributes($sale, $installment));
        }
    }

    /**
     * Columnas de un ítem vendido. Todo lo vendido queda pendiente de compra.
     *
     * @param  ItemInput  $item
     * @param  Line  $line
     * @return array<string, mixed>
     */
    public function itemAttributes(array $item, array $line): array
    {
        return [
            'product_id' => (int) $item['product_id'],
            'quantity' => $line['quantity'],
            'unit_cost' => $line['unit_cost'],
            'unit_freight' => $line['unit_freight'],
            'unit_margin' => $line['unit_margin'],
            'unit_price' => $line['unit_price'],
            'subtotal' => bcmul((string) $line['quantity'], $line['unit_price'], 2),
            'pending_quantity' => $line['quantity'],
        ];
    }

    /**
     * Columnas de una cuota nueva (sin nada pagado).
     *
     * @param  array{number: int, due_date: CarbonInterface, amount: string, cost_portion: string}  $installment
     * @return array<string, mixed>
     */
    public function installmentAttributes(Sale $sale, array $installment): array
    {
        return [
            'customer_id' => $sale->customer_id,
            'number' => $installment['number'],
            'due_date' => $installment['due_date'],
            'amount' => $installment['amount'],
            'cost_portion' => $installment['cost_portion'],
            'paid_amount' => '0.00',
            'status' => InstallmentStatus::Pending,
        ];
    }

    /**
     * El anticipo se registra como cobro (con su crédito en la cuenta corriente del cliente de la venta).
     */
    public function registerDownPayment(
        Sale $sale,
        string $amount,
        PaymentMethod|string|null $method,
        CarbonInterface|string $date,
        User $user,
    ): Payment {
        $method ??= PaymentMethod::Cash;

        $payment = Payment::create([
            'customer_id' => $sale->customer_id,
            'sale_id' => $sale->getKey(),
            'date' => $date,
            'amount' => $amount,
            'method' => $method instanceof PaymentMethod ? $method : PaymentMethod::from($method),
            'collected_by' => $user->getKey(),
            'notes' => "Anticipo de la venta {$sale->number()}",
        ]);

        $this->accounts->record(
            $sale->customer,
            AccountMovementType::Payment,
            "Anticipo venta {$sale->number()}",
            credit: $amount,
            reference: $payment,
            user: $user,
            date: $date,
        );

        return $payment;
    }

    private function money(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
