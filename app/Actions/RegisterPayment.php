<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\PaymentAllocator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegisterPayment
{
    public function __construct(
        private readonly PaymentAllocator $allocator,
        private readonly AccountService $accounts,
    ) {}

    /**
     * Registra un cobro y lo imputa a las cuotas del cliente, la de vencimiento más viejo primero.
     * Admite pagos parciales y adelantados; no admite cobrar más que la deuda total.
     *
     * @param  array{
     *     customer_id: int,
     *     amount: string|int|float,
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
                "Cobro #{$payment->getKey()}",
                credit: $amount,
                reference: $payment,
                user: $user,
                date: $date,
            );

            return $payment->load('installments');
        });
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
