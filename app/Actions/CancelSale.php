<?php

namespace App\Actions;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSale
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Anula una venta: quita de "pendientes de compra" lo que todavía no se pidió, anula sus cuotas, anula el anticipo (si hubo) y deja
     * en la cuenta corriente los movimientos que revierten la venta. La venta no se borra:
     * queda como historial con quién, cuándo y por qué. Lo que ya estaba en una orden de compra
     * queda en la orden (ya se le pidió al proveedor); si no llega, no vuelve a pendiente.
     *
     * No se puede anular si ya tiene cobros imputados a sus cuotas: hay que anular esos cobros
     * primero, así cada devolución de dinero queda registrada por separado.
     *
     * @throws ValidationException
     */
    public function handle(Sale $sale, string $reason, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user): Sale {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['sale' => 'Esta venta ya está anulada.']);
            }

            $installments = $locked->installments()->lockForUpdate()->get();

            $this->ensureNoInstallmentPayments($locked, $installments->sum(fn ($installment): float => (float) $installment->paid_amount) > 0);

            $locked->items()->update(['pending_quantity' => 0]);

            $locked->installments()->update(['status' => InstallmentStatus::Cancelled->value]);

            $locked->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

            // Revierte el débito por el total de la venta.
            $this->accounts->record(
                $locked->customer,
                AccountMovementType::Adjustment,
                "Anulación de la venta {$locked->number()}",
                credit: $locked->total,
                reference: $locked,
                user: $user,
            );

            $this->cancelDownPayment($locked, $user);

            $this->activity->log(
                ActivityEvent::Cancelled,
                "Anuló la venta {$locked->number()} de ".Money::format($locked->total)." a {$locked->customer->name}: {$reason}",
                $locked,
                ['reason' => $reason, 'total' => $locked->total],
                $user,
            );

            return $locked->refresh();
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureNoInstallmentPayments(Sale $sale, bool $hasPayments): void
    {
        if (! $hasPayments) {
            return;
        }

        $paymentIds = Payment::query()
            ->notCancelled()
            ->whereHas('installments', fn ($query) => $query->where('installments.sale_id', $sale->getKey()))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (int $id): string => Payment::numberFor($id))
            ->implode(', ');

        throw ValidationException::withMessages([
            'sale' => "La venta tiene cobros imputados a sus cuotas. Anulá primero los cobros {$paymentIds} y después la venta.",
        ]);
    }

    /**
     * El anticipo se anula con la venta: queda marcado y se revierte su crédito en la cuenta corriente.
     */
    private function cancelDownPayment(Sale $sale, User $user): void
    {
        $downPayment = Payment::query()->notCancelled()->where('sale_id', $sale->getKey())->lockForUpdate()->first();

        if ($downPayment === null) {
            return;
        }

        $downPayment->update([
            'cancelled_at' => now(),
            'cancelled_by' => $user->getKey(),
            'cancellation_reason' => "Anulación de la venta {$sale->number()}",
        ]);

        $this->accounts->record(
            $sale->customer,
            AccountMovementType::Adjustment,
            "Anulación del anticipo de la venta {$sale->number()}",
            debit: $downPayment->amount,
            reference: $downPayment,
            user: $user,
        );
    }
}
