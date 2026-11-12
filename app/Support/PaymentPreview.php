<?php

namespace App\Support;

use App\Models\Installment;
use App\Models\Sale;
use App\Services\PaymentAllocator;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Lo que ve quien cobra antes de confirmar: la deuda del cliente, las cuotas que puede elegir,
 * cuánto suman y cómo se imputaría el monto. Usa el mismo PaymentAllocator que el cobro real.
 */
class PaymentPreview
{
    /**
     * Resumen de la deuda del cliente: total, cuotas pendientes y cuánto está vencido.
     */
    public static function debt(mixed $customerId): HtmlString
    {
        if (blank($customerId)) {
            return new HtmlString('<span class="text-sm text-gray-500">Elegí un cliente para ver su deuda.</span>');
        }

        $installments = self::pending($customerId);

        if ($installments->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">El cliente no tiene cuotas pendientes.</span>');
        }

        $total = self::sum($installments);
        $overdue = $installments->filter(fn (Installment $installment): bool => $installment->isOverdue());
        $count = $installments->count();

        $detail = $count.($count === 1 ? ' cuota pendiente' : ' cuotas pendientes');

        if ($overdue->isNotEmpty()) {
            $detail .= ' · <span style="color:#dc2626; font-weight:600">'.$overdue->count()
                .($overdue->count() === 1 ? ' vencida' : ' vencidas').' por '.Money::format(self::sum($overdue)).'</span>';
        }

        return new HtmlString(
            '<div class="text-sm"><div style="font-weight:700; font-size:1.15rem">Deuda total: '.Money::format($total).'</div>'
            .'<div style="margin-top:2px">'.$detail.'</div></div>',
        );
    }

    /**
     * Las cuotas pendientes del cliente para elegir, de la más vieja a la más nueva.
     *
     * @return array<int, string> id de cuota => título
     */
    public static function options(mixed $customerId): array
    {
        return self::pending($customerId)
            ->mapWithKeys(fn (Installment $installment): array => [
                $installment->getKey() => 'Venta '.Sale::numberFor($installment->sale_id).' · cuota '.$installment->number.' de '.$installment->sale->installments_count,
            ])
            ->all();
    }

    /**
     * Descripción de cada cuota elegible: vencimiento, atraso y saldo.
     *
     * @return array<int, string> id de cuota => descripción
     */
    public static function descriptions(mixed $customerId): array
    {
        return self::pending($customerId)
            ->mapWithKeys(fn (Installment $installment): array => [
                $installment->getKey() => 'Vence '.$installment->due_date->format('d/m/Y')
                    .($installment->isOverdue() ? ' · VENCIDA hace '.(int) $installment->due_date->diffInDays(today()).' días' : '')
                    .' · Saldo '.Money::format($installment->balance()),
            ])
            ->all();
    }

    /**
     * Ids de las cuotas vencidas del cliente.
     *
     * @return list<string>
     */
    public static function overdueIds(mixed $customerId): array
    {
        return self::pending($customerId)
            ->filter(fn (Installment $installment): bool => $installment->isOverdue())
            ->map(fn (Installment $installment): string => (string) $installment->getKey())
            ->values()
            ->all();
    }

    /**
     * Total a pagar por las cuotas elegidas: la suma de sus saldos.
     *
     * @param  array<int|string>  $installmentIds
     */
    public static function total(mixed $customerId, array $installmentIds): string
    {
        return self::sum(self::pending($customerId)->whereIn('id', array_map('intval', $installmentIds)));
    }

    /**
     * A qué cuotas se imputaría el monto: a las elegidas o, si no se eligió ninguna, a toda la
     * deuda desde la más vieja.
     *
     * @param  array<int|string>  $installmentIds
     */
    public static function allocation(mixed $customerId, mixed $amount, array $installmentIds = []): HtmlString
    {
        if (blank($customerId) || ! is_numeric($amount) || (float) $amount <= 0) {
            return new HtmlString('<span class="text-sm text-gray-500">Ingresá un monto para ver a qué cuotas se imputa.</span>');
        }

        $installments = self::pending($customerId);
        $chosen = array_map('intval', $installmentIds);

        if ($chosen !== []) {
            $installments = $installments->whereIn('id', $chosen)->values();
        }

        try {
            $allocations = (new PaymentAllocator)->allocate(
                $installments->map(fn (Installment $i): array => ['id' => $i->getKey(), 'balance' => $i->balance()])->values()->all(),
                $amount,
            );
        } catch (InvalidArgumentException $exception) {
            $message = $chosen !== [] && str_contains($exception->getMessage(), 'supera')
                ? 'El monto supera el saldo de las cuotas elegidas ('.Money::format(self::sum($installments)).').'
                : $exception->getMessage();

            return new HtmlString('<span class="text-sm" style="color:#dc2626">'.e($message).'</span>');
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
     * @param  Collection<int, Installment>  $installments
     */
    private static function sum(Collection $installments): string
    {
        return $installments->reduce(fn (string $carry, Installment $installment): string => bcadd($carry, $installment->balance(), 2), '0.00');
    }

    /**
     * @return Collection<int, Installment>
     */
    private static function pending(mixed $customerId): Collection
    {
        if (blank($customerId)) {
            return new Collection;
        }

        return Installment::query()
            ->where('customer_id', $customerId)
            ->unpaid()
            ->with('sale:id,installments_count')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }
}
