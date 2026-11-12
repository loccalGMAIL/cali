<?php

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cobros realizados en un período (incluye anticipos; excluye cobros anulados), con los
 * subtotales por medio de pago y por quien cobró que hacen falta para cerrar la caja.
 */
class PaymentsReport implements Report
{
    public static function slug(): string
    {
        return 'payments';
    }

    public function title(): string
    {
        return 'Cobros realizados';
    }

    /**
     * @return array{from: string, to: string, method: ?string, collected_by: ?int}
     */
    public function normalize(array $input): array
    {
        [$from, $to] = ReportFilters::dateRange($input);
        $method = $input['method'] ?? null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'method' => is_string($method) ? PaymentMethod::tryFrom($method)?->value : null,
            'collected_by' => ReportFilters::integer($input['collected_by'] ?? null),
        ];
    }

    public function describe(array $filters): array
    {
        return array_filter([
            'Período' => ReportFilters::period($filters['from'], $filters['to']),
            'Medio de pago' => $filters['method'] === null ? null : PaymentMethod::from($filters['method'])->getLabel(),
            'Cobró' => $filters['collected_by'] === null ? null : User::query()->whereKey($filters['collected_by'])->value('name'),
        ]);
    }

    /**
     * @return array{
     *     payments: Collection<int, Payment>,
     *     total: string,
     *     by_method: array<string, string>,
     *     by_collector: array<string, string>
     * }
     */
    public function build(array $filters): array
    {
        $payments = Payment::query()
            ->notCancelled()
            ->whereDate('date', '>=', $filters['from'])
            ->whereDate('date', '<=', $filters['to'])
            ->when($filters['method'] !== null, fn (Builder $query) => $query->where('method', $filters['method']))
            ->when($filters['collected_by'] !== null, fn (Builder $query) => $query->where('collected_by', $filters['collected_by']))
            ->with(['customer:id,name', 'collector:id,name', 'installments.sale.items.product', 'sale.items.product'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $total = '0.00';
        $byMethod = [];
        $byCollector = [];

        foreach ($payments as $payment) {
            $method = $payment->method->getLabel();
            $collector = $payment->collector->name ?? '—';

            $total = bcadd($total, $payment->amount, 2);
            $byMethod[$method] = bcadd($byMethod[$method] ?? '0.00', $payment->amount, 2);
            $byCollector[$collector] = bcadd($byCollector[$collector] ?? '0.00', $payment->amount, 2);
        }

        ksort($byMethod);
        ksort($byCollector);

        return ['payments' => $payments, 'total' => $total, 'by_method' => $byMethod, 'by_collector' => $byCollector];
    }
}
