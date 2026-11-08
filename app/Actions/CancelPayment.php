<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\SaleStatus;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPayment
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Anula un cobro: devuelve a cada cuota lo que se le había imputado, reabre las ventas que
     * habían quedado pagadas y deja un débito en la cuenta corriente. El cobro no se borra:
     * queda marcado como anulado, con quién, cuándo y por qué.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $user): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['payment' => 'Este cobro ya está anulado.']);
            }

            if ($locked->isDownPayment()) {
                throw ValidationException::withMessages([
                    'payment' => "Este cobro es el anticipo de la venta #{$locked->sale_id}: para anularlo hay que anular la venta.",
                ]);
            }

            // Cuánto de este cobro se imputó a cada cuota: [id de cuota => monto].
            $imputations = DB::table('installment_payment')
                ->where('payment_id', $locked->getKey())
                ->pluck('amount', 'installment_id');

            // Bloqueo de las cuotas en orden de id, para no cruzarse con otro cobro en curso.
            $installments = Installment::query()
                ->whereIn('id', $imputations->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $saleIds = [];

            foreach ($installments as $installment) {
                $applied = number_format((float) $imputations[$installment->getKey()], 2, '.', '');

                $installment->paid_amount = bcsub($installment->paid_amount, $applied, 2);
                $installment->status = bccomp($installment->paid_amount, '0', 2) <= 0
                    ? InstallmentStatus::Pending
                    : InstallmentStatus::Partial;
                $installment->save();

                $saleIds[$installment->sale_id] = true;
            }

            // Las ventas que estaban pagadas vuelven a estar en curso.
            Sale::query()
                ->whereIn('id', array_keys($saleIds))
                ->where('status', SaleStatus::Paid->value)
                ->update(['status' => SaleStatus::Active->value]);

            $locked->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

            $this->accounts->record(
                $locked->customer,
                AccountMovementType::Adjustment,
                "Anulación del cobro #{$locked->getKey()}",
                debit: $locked->amount,
                reference: $locked,
                user: $user,
            );

            $this->activity->log(
                ActivityEvent::Cancelled,
                "Anuló el cobro #{$locked->getKey()} de ".Money::format($locked->amount)." a {$locked->customer->name}: {$reason}",
                $locked,
                ['reason' => $reason, 'amount' => $locked->amount],
                $user,
            );

            return $locked->refresh();
        });
    }
}
