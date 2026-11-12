<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Services\PaymentAllocator;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegisterPayment
{
    public function __construct(
        private readonly PaymentAllocator $allocator,
        private readonly AccountService $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Registra un cobro y lo imputa a las cuotas del cliente, la de vencimiento más viejo primero.
     * Admite pagos parciales y adelantados; no admite cobrar más que la deuda total.
     *
     * Si se indican cuotas (`installment_ids`), el cobro se imputa solo a esas, también de la más
     * vieja a la más nueva, y no puede superar su saldo. Sin cuotas indicadas, se imputa a toda la deuda.
     *
     * @param  array{
     *     customer_id: int,
     *     amount: string|int|float,
     *     installment_ids?: array<int|string>|null,
     *     date?: CarbonInterface|string|null,
     *     method?: PaymentMethod|string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $user): Payment
    {
        return DB::transaction(function () use ($data, $user): Payment {
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);

            // Cuotas con saldo, de la más vieja a la más nueva, bloqueadas contra cobros simultáneos.
            $installments = Installment::query()
                ->where('customer_id', $customer->getKey())
                ->unpaid()
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $selected = array_values(array_unique(array_map('intval', $data['installment_ids'] ?? [])));

            if ($selected !== []) {
                $installments = $this->restrictToSelection($installments, $selected, $data['amount']);
            }

            try {
                $allocations = $this->allocator->allocate(
                    $installments->map(fn (Installment $installment): array => [
                        'id' => $installment->getKey(),
                        'balance' => $installment->balance(),
                    ])->values()->all(),
                    $data['amount'],
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            $amount = number_format((float) $data['amount'], 2, '.', '');
            $method = $data['method'] ?? PaymentMethod::Cash;
            $date = $data['date'] ?? today();

            $payment = Payment::create([
                'customer_id' => $customer->getKey(),
                'date' => $date,
                'amount' => $amount,
                'method' => $method instanceof PaymentMethod ? $method : PaymentMethod::from((string) $method),
                'collected_by' => $user->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);

            $saleIds = [];

            foreach ($allocations as $allocation) {
                $installment = $installments[$allocation['installment_id']];

                $installment->paid_amount = bcadd($installment->paid_amount, $allocation['applied'], 2);
                $installment->status = bccomp($allocation['remaining'], '0', 2) === 0
                    ? InstallmentStatus::Paid
                    : InstallmentStatus::Partial;
                $installment->save();

                $payment->installments()->attach($installment->getKey(), ['amount' => $allocation['applied']]);

                $saleIds[$installment->sale_id] = true;
            }

            $this->closePaidSales(array_keys($saleIds));

            $this->accounts->record(
                $customer,
                AccountMovementType::Payment,
                "Cobro {$payment->number()}",
                credit: $amount,
                reference: $payment,
                user: $user,
                date: $date,
            );

            $this->activity->log(
                ActivityEvent::Created,
                "Registró el cobro {$payment->number()} de ".Money::format($amount)." a {$customer->name}",
                $payment,
                ['amount' => $amount, 'method' => $payment->method->value, 'installments' => count($allocations), 'chosen_installments' => $selected !== []],
                $user,
            );

            return $payment->load('installments');
        });
    }

    /**
     * Deja solo las cuotas elegidas, si siguen pendientes y son del cliente, y verifica que el monto
     * no supere su saldo.
     *
     * @param  Collection<int, Installment>  $installments  Las cuotas pendientes del cliente, por id.
     * @param  list<int>  $selected
     * @return Collection<int, Installment>
     *
     * @throws ValidationException
     */
    private function restrictToSelection(Collection $installments, array $selected, string|int|float $amount): Collection
    {
        if (array_diff($selected, $installments->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'installment_ids' => 'Alguna de las cuotas elegidas ya no está pendiente o no es de este cliente.',
            ]);
        }

        $chosen = $installments->only($selected)->keyBy('id');
        $balance = $chosen->reduce(fn (string $carry, Installment $installment): string => bcadd($carry, $installment->balance(), 2), '0.00');

        if (bccomp(number_format((float) $amount, 2, '.', ''), $balance, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => 'El monto supera el saldo de las cuotas elegidas ('.Money::format($balance).').',
            ]);
        }

        return $chosen;
    }

    /**
     * Una venta queda pagada cuando todas sus cuotas están pagadas.
     *
     * @param  list<int>  $saleIds
     */
    private function closePaidSales(array $saleIds): void
    {
        foreach ($saleIds as $saleId) {
            $hasUnpaid = Installment::query()->where('sale_id', $saleId)->unpaid()->exists();

            if (! $hasUnpaid) {
                Sale::query()->whereKey($saleId)->where('status', SaleStatus::Active->value)->update(['status' => SaleStatus::Paid->value]);
            }
        }
    }
}
