<?php

namespace App\Support;

use App\Models\Installment;
use App\Services\PaymentAllocator;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Vista previa del cobro: deuda del cliente y cómo se imputaría el monto (FIFO).
 * Usa el mismo PaymentAllocator que el cobro real.
 */
class PaymentPreview
{
    public static function debt(mixed $customerId): HtmlString
    {
        if (blank($customerId)) {
            return new HtmlString('<span class="text-sm text-gray-500">Elegí un cliente para ver su deuda.</span>');
        }

        $installments = self::pending($customerId);

        if ($installments->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">El cliente no tiene cuotas pendientes.</span>');
        }

        $total = $installments->reduce(fn (string $carry, Installment $i): string => bcadd($carry, $i->balance(), 2), '0.00');

        $rows = $installments->map(fn (Installment $installment): string => '<tr>'
            .'<td style="padding:2px 12px 2px 0">Venta #'.$installment->sale_id.' · cuota '.$installment->number.'</td>'
            .'<td style="padding:2px 12px;'.($installment->isOverdue() ? ' color:#dc2626; font-weight:600' : '').'">'
            .$installment->due_date->format('d/m/Y').($installment->isOverdue() ? ' (vencida)' : '').'</td>'
            .'<td style="padding:2px 0 2px 12px; text-align:right">'.Money::format($installment->balance()).'</td>'
            .'</tr>')->implode('');

        return new HtmlString(
            '<div class="text-sm" style="max-width:520px">'
            .'<div style="font-weight:700; font-size:1.05rem; margin-bottom:6px">Deuda total: '.Money::format($total).'</div>'
            .'<table><thead><tr style="text-align:left"><th style="padding:2px 12px 2px 0">Cuota</th><th style="padding:2px 12px">Vence</th>'
            .'<th style="padding:2px 0 2px 12px; text-align:right">Saldo</th></tr></thead><tbody>'.$rows.'</tbody></table></div>',
        );
    }

    public static function allocation(mixed $customerId, mixed $amount): HtmlString
    {
        if (blank($customerId) || ! is_numeric($amount) || (float) $amount <= 0) {
            return new HtmlString('<span class="text-sm text-gray-500">Ingresá un monto para ver a qué cuotas se imputa.</span>');
        }

        $installments = self::pending($customerId);

        try {
            $allocations = (new PaymentAllocator)->allocate(
                $installments->map(fn (Installment $i): array => ['id' => $i->getKey(), 'balance' => $i->balance()])->values()->all(),
                $amount,
            );
        } catch (InvalidArgumentException $exception) {
            return new HtmlString('<span class="text-sm" style="color:#dc2626">'.e($exception->getMessage()).'</span>');
        }

        $byId = $installments->keyBy('id');

        $rows = collect($allocations)->map(function (array $allocation) use ($byId): string {
            $installment = $byId[$allocation['installment_id']];
            $state = bccomp($allocation['remaining'], '0', 2) === 0 ? 'queda pagada' : 'queda con saldo '.Money::format($allocation['remaining']);

            return '<tr>'
                .'<td style="padding:2px 12px 2px 0">Venta #'.$installment->sale_id.' · cuota '.$installment->number.'</td>'
                .'<td style="padding:2px 12px; text-align:right">'.Money::format($allocation['applied']).'</td>'
                .'<td style="padding:2px 0 2px 12px">'.e($state).'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="text-sm" style="max-width:520px"><table><thead><tr style="text-align:left">'
            .'<th style="padding:2px 12px 2px 0">Se imputa a</th><th style="padding:2px 12px; text-align:right">Monto</th><th style="padding:2px 0 2px 12px">Resultado</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>',
        );
    }

    /**
     * @return Collection<int, Installment>
     */
    private static function pending(mixed $customerId): Collection
    {
        return Installment::query()
            ->where('customer_id', $customerId)
            ->unpaid()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }
}
