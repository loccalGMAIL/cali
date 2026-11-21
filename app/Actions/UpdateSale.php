<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Services\SaleCalculator;
use App\Services\SaleRecorder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ItemInput from SaleRecorder
 * @phpstan-import-type Line from SaleRecorder
 */
class UpdateSale
{
    public function __construct(
        private readonly SaleRecorder $recorder,
        private readonly AccountService $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Edita una venta confirmada conservando su número. Todo ocurre en una única transacción.
     *
     * El vendedor, la fecha y las notas se pueden cambiar siempre. Cambiar la fecha también mueve
     * la del anticipo y la de los movimientos de la venta en la cuenta corriente.
     *
     * El cliente, los productos, el plan, el anticipo y los vencimientos rearman la venta como si
     * se hubiera cargado de nuevo: se recalculan los totales y las cuotas; si cambió el total o el
     * cliente, se revierte el importe anterior en la cuenta corriente y se registra el nuevo; si
     * cambió el anticipo, el anterior se anula y se registra otro cobro. Solo se puede si las
     * cuotas no tienen cobros imputados y, para cambiar los productos, si no hay nada de la venta
     * en una orden de compra no anulada.
     *
     * Lo que no viene en `$data` (o `$items` en null) queda como está.
     *
     * @param  array{
     *     customer_id?: int|string,
     *     seller_id?: int|string,
     *     financing_plan_id?: int|string,
     *     date?: CarbonInterface|string,
     *     first_due_date?: CarbonInterface|string,
     *     down_payment?: string|int|float|null,
     *     down_payment_method?: PaymentMethod|string|null,
     *     notes?: string|null
     * }  $data
     * @param  list<ItemInput>|null  $items
     *
     * @throws ValidationException
     */
    public function handle(Sale $sale, array $data, ?array $items, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items, $user): Sale {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['sale' => 'Una venta anulada no se puede editar.']);
            }

            $downPayment = $locked->activeDownPayment();
            $before = $this->snapshot($locked, $downPayment);

            $date = CarbonImmutable::parse($data['date'] ?? $locked->date)->toDateString();
            $terms = $this->terms($locked, $data, $downPayment);
            $currentItems = $this->currentItems($locked);
            $newItems = $items === null ? $currentItems : $this->normalizeItems($items);

            $itemsChanged = $newItems !== $currentItems;
            $termsChanged = $this->termsChanged($locked, $terms, $downPayment);

            if ($itemsChanged || $termsChanged) {
                $this->ensureTermsCanChange($locked, $itemsChanged);
                $this->rebuild($locked, $terms, $newItems, $itemsChanged, $date, $downPayment, $user);
            }

            $dateChanged = $date !== $locked->date->toDateString();

            $locked->update([
                'seller_id' => (int) ($data['seller_id'] ?? $locked->seller_id),
                'date' => $date,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
            ]);

            if ($dateChanged) {
                $this->moveDates($locked, $date);
            }

            $changes = $this->changes($before, $this->snapshot($locked->refresh(), $locked->activeDownPayment()));

            if ($changes !== []) {
                $this->activity->log(
                    ActivityEvent::Updated,
                    "Editó la venta {$locked->number()} a {$locked->customer->name}",
                    $locked,
                    ['changes' => $changes],
                    $user,
                );
            }

            return $locked->load(['items', 'installments']);
        });
    }

    /**
     * Condiciones pedidas, completando con las actuales lo que no viene.
     *
     * @param  array<string, mixed>  $data
     * @return array{customer_id: int, financing_plan_id: int, first_due_date: string, down_payment: string, down_payment_method: PaymentMethod}
     */
    private function terms(Sale $sale, array $data, ?Payment $downPayment): array
    {
        $method = $data['down_payment_method'] ?? $downPayment?->method ?? PaymentMethod::Cash;

        return [
            'customer_id' => (int) ($data['customer_id'] ?? $sale->customer_id),
            'financing_plan_id' => (int) ($data['financing_plan_id'] ?? $sale->financing_plan_id),
            'first_due_date' => SaleCalculator::dueDateFor($data['first_due_date'] ?? $sale->first_due_date)->toDateString(),
            'down_payment' => $this->money(array_key_exists('down_payment', $data) ? $data['down_payment'] : $sale->down_payment),
            'down_payment_method' => $method instanceof PaymentMethod ? $method : PaymentMethod::from((string) $method),
        ];
    }

    /**
     * @param  array{customer_id: int, financing_plan_id: int, first_due_date: string, down_payment: string, down_payment_method: PaymentMethod}  $terms
     */
    private function termsChanged(Sale $sale, array $terms, ?Payment $downPayment): bool
    {
        return $terms['customer_id'] !== $sale->customer_id
            || $terms['financing_plan_id'] !== $sale->financing_plan_id
            || $terms['first_due_date'] !== $sale->first_due_date->toDateString()
            || bccomp($terms['down_payment'], $sale->down_payment, 2) !== 0
            || ($downPayment !== null && $terms['down_payment_method'] !== $downPayment->method);
    }

    /**
     * @throws ValidationException
     */
    private function ensureTermsCanChange(Sale $sale, bool $itemsChanged): void
    {
        $reason = $sale->installmentPaymentsLockReason() ?? ($itemsChanged ? $sale->purchaseOrdersLockReason() : null);

        if ($reason !== null) {
            throw ValidationException::withMessages(['sale' => $reason]);
        }
    }

    /**
     * Rearma la venta con las condiciones nuevas, como si se hubiera cargado de nuevo.
     *
     * @param  array{customer_id: int, financing_plan_id: int, first_due_date: string, down_payment: string, down_payment_method: PaymentMethod}  $terms
     * @param  list<ItemInput>  $items
     *
     * @throws ValidationException
     */
    private function rebuild(Sale $sale, array $terms, array $items, bool $itemsChanged, string $date, ?Payment $downPayment, User $user): void
    {
        if ($itemsChanged) {
            $this->recorder->ensureItemsAreValid($items, $sale->items()->pluck('product_id')->map(fn ($id): int => (int) $id)->all());
        }

        // El plan queda congelado en la venta: solo si se elige otro se toma su cantidad de cuotas actual.
        $installmentsCount = $terms['financing_plan_id'] === $sale->financing_plan_id
            ? $sale->installments_count
            : FinancingPlan::query()->findOrFail($terms['financing_plan_id'])->installments_count;

        ['lines' => $lines, 'result' => $result] = $this->recorder->calculate(
            $items,
            $terms['down_payment'],
            $installmentsCount,
            $terms['first_due_date'],
        );

        $previousCustomer = $sale->customer;
        $previousTotal = $sale->total;
        $newCustomer = Customer::query()->findOrFail($terms['customer_id']);
        $customerChanged = ! $newCustomer->is($previousCustomer);

        $downPaymentChanged = $customerChanged
            || bccomp($result['down_payment'], $sale->down_payment, 2) !== 0
            || ($downPayment !== null && $terms['down_payment_method'] !== $downPayment->method);

        if ($customerChanged || bccomp($previousTotal, $result['total'], 2) !== 0) {
            $this->accounts->record(
                $previousCustomer,
                AccountMovementType::Adjustment,
                "Modificación de la venta {$sale->number()}: se revierte el importe anterior",
                credit: $previousTotal,
                reference: $sale,
                user: $user,
            );
        }

        if ($downPaymentChanged && $downPayment !== null) {
            $this->cancelDownPayment($sale, $downPayment, $user);
        }

        $sale->update([
            'customer_id' => $newCustomer->getKey(),
            'financing_plan_id' => $terms['financing_plan_id'],
            'installments_count' => $installmentsCount,
            'total' => $result['total'],
            'total_cost' => $result['total_cost'],
            'total_freight' => $result['total_freight'],
            'down_payment' => $result['down_payment'],
            'financed_amount' => $result['financed_amount'],
            'first_due_date' => $terms['first_due_date'],
            'status' => $result['installments'] === [] ? SaleStatus::Paid : SaleStatus::Active,
        ]);
        $sale->setRelation('customer', $newCustomer);

        if ($customerChanged || bccomp($previousTotal, $result['total'], 2) !== 0) {
            $this->accounts->record(
                $newCustomer,
                AccountMovementType::Sale,
                "Venta {$sale->number()} (modificada)",
                debit: $result['total'],
                reference: $sale,
                user: $user,
                date: $date,
            );
        }

        if ($itemsChanged) {
            $this->replaceItems($sale, $items, $lines);
        }

        $this->replaceInstallments($sale, $result['installments']);

        if ($downPaymentChanged && bccomp($result['down_payment'], '0', 2) > 0) {
            $this->recorder->registerDownPayment($sale, $result['down_payment'], $terms['down_payment_method'], $date, $user);
        }
    }

    /**
     * Reemplaza los ítems reutilizando las filas existentes (primero las del mismo producto), así
     * lo que figura en órdenes de compra anuladas sigue apuntando a un ítem de la venta.
     *
     * @param  list<ItemInput>  $items
     * @param  list<Line>  $lines
     *
     * @throws ValidationException
     */
    private function replaceItems(Sale $sale, array $items, array $lines): void
    {
        /** @var Collection<int, SaleItem> $available */
        $available = $sale->items()->withCount('purchaseOrderItems')->orderBy('id')->get()
            ->sortByDesc('purchase_order_items_count')
            ->values();

        $pending = [];

        foreach ($items as $index => $item) {
            $match = $available->search(fn (SaleItem $saleItem): bool => $saleItem->product_id === (int) $item['product_id']);

            if ($match === false) {
                $pending[] = $index;

                continue;
            }

            $available->pull($match)->update($this->recorder->itemAttributes($item, $lines[$index]));
        }

        foreach ($pending as $index) {
            $reused = $available->shift();
            $attributes = $this->recorder->itemAttributes($items[$index], $lines[$index]);

            $reused !== null ? $reused->update($attributes) : $sale->items()->create($attributes);
        }

        foreach ($available as $leftover) {
            if ($leftover->purchase_order_items_count > 0) {
                throw ValidationException::withMessages([
                    'items' => "No se puede quitar «{$leftover->product->name}» de la venta: figura en una orden de compra anulada, que queda como historial.",
                ]);
            }

            $leftover->delete();
        }
    }

    /**
     * Reemplaza las cuotas reutilizando las filas por número. Una cuota que tuvo cobros (ya
     * anulados) conserva su imputación como historial, así que no se puede borrar.
     *
     * @param  list<array{number: int, due_date: CarbonInterface, amount: string, cost_portion: string}>  $installments
     *
     * @throws ValidationException
     */
    private function replaceInstallments(Sale $sale, array $installments): void
    {
        $existing = $sale->installments()->withCount('payments')->get()->keyBy('number');

        foreach ($installments as $installment) {
            $attributes = $this->recorder->installmentAttributes($sale, $installment);
            $current = $existing->pull($installment['number']);

            $current !== null ? $current->update($attributes) : $sale->installments()->create($attributes);
        }

        $leftovers = $existing->sortKeys();
        $withHistory = $leftovers->first(fn (Installment $installment): bool => $installment->payments_count > 0);

        if ($withHistory !== null) {
            throw ValidationException::withMessages([
                'financing_plan_id' => "La cuota {$withHistory->number} tuvo cobros (ya anulados) que quedan como historial: la venta no puede quedar con menos de {$withHistory->number} cuotas.",
            ]);
        }

        Installment::query()->whereKey($leftovers->modelKeys())->delete();
    }

    /**
     * El anticipo anterior se anula y se revierte su crédito en la cuenta corriente.
     */
    private function cancelDownPayment(Sale $sale, Payment $downPayment, User $user): void
    {
        $downPayment->update([
            'cancelled_at' => now(),
            'cancelled_by' => $user->getKey(),
            'cancellation_reason' => "Modificación de la venta {$sale->number()}",
        ]);

        $this->accounts->record(
            $downPayment->customer,
            AccountMovementType::Adjustment,
            "Anulación del anticipo de la venta {$sale->number()} por modificación",
            debit: $downPayment->amount,
            reference: $downPayment,
            user: $user,
        );
    }

    /**
     * El anticipo y los cargos de la venta en la cuenta corriente llevan la fecha de la venta.
     * Los saldos no cambian: solo la fecha con la que se muestran.
     */
    private function moveDates(Sale $sale, string $date): void
    {
        AccountMovement::query()
            ->whereMorphedTo('reference', $sale)
            ->where('type', AccountMovementType::Sale->value)
            ->update(['date' => $date]);

        $downPayment = $sale->activeDownPayment();

        if ($downPayment === null) {
            return;
        }

        $downPayment->update(['date' => $date]);

        AccountMovement::query()
            ->whereMorphedTo('reference', $downPayment)
            ->where('type', AccountMovementType::Payment->value)
            ->update(['date' => $date]);
    }

    /**
     * @return list<array{product_id: int, quantity: int, unit_cost: string, unit_freight: string, unit_margin: string}>
     */
    private function currentItems(Sale $sale): array
    {
        return $sale->items()->orderBy('id')->get()
            ->map(fn (SaleItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantity' => $item->quantity,
                'unit_cost' => $this->money($item->unit_cost),
                'unit_freight' => $this->money($item->unit_freight),
                'unit_margin' => $this->money($item->unit_margin),
            ])
            ->all();
    }

    /**
     * @param  list<ItemInput>  $items
     * @return list<array{product_id: int, quantity: int, unit_cost: string, unit_freight: string, unit_margin: string}>
     */
    private function normalizeItems(array $items): array
    {
        return array_values(array_map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
            'unit_cost' => $this->money($item['unit_cost']),
            'unit_freight' => $this->money($item['unit_freight'] ?? 0),
            'unit_margin' => $this->money($item['unit_margin']),
        ], $items));
    }

    /**
     * Lo que se muestra en el registro de actividad, para comparar antes y después.
     *
     * @return array<string, string|null>
     */
    private function snapshot(Sale $sale, ?Payment $downPayment): array
    {
        $sale->loadMissing(['customer', 'seller', 'financingPlan', 'items.product']);

        return [
            'customer' => $sale->customer->name,
            'seller' => $sale->seller->name,
            'date' => $sale->date->format('d/m/Y'),
            'financing_plan' => "{$sale->financingPlan->name} ({$sale->installments_count} cuotas)",
            'first_due_date' => $sale->first_due_date->format('d/m/Y'),
            'items' => $sale->items->sortBy('id')
                ->map(fn (SaleItem $item): string => "{$item->quantity} × {$item->product->name} a ".Money::format($item->unit_price))
                ->implode('; '),
            'total' => $sale->total,
            'down_payment' => $sale->down_payment,
            'down_payment_method' => $downPayment?->method->getLabel(),
            'notes' => $sale->notes,
        ];
    }

    /**
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return array<string, array{old: string|null, new: string|null}>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $attribute => $value) {
            if ($before[$attribute] !== $value) {
                $changes[$attribute] = ['old' => $before[$attribute], 'new' => $value];
            }
        }

        return $changes;
    }

    private function money(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
