<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Installment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cobranza del mes: la lista para salir a cobrar. Las cuotas impagas que vencen en el mes elegido
 * y, si se pide, las que quedaron atrasadas de meses anteriores, agrupadas por cliente.
 */
class MonthlyCollectionReport implements Report
{
    public static function slug(): string
    {
        return 'monthly-collection';
    }

    public function title(): string
    {
        return 'Cobranza del mes';
    }

    /**
     * @return array{month: string, include_overdue: bool, city: ?string}
     */
    public function normalize(array $input): array
    {
        return [
            'month' => ReportFilters::month($input['month'] ?? null)->format('Y-m'),
            'include_overdue' => ReportFilters::boolean($input['include_overdue'] ?? null, true),
            'city' => ReportFilters::text($input['city'] ?? null),
        ];
    }

    public function describe(array $filters): array
    {
        return array_filter([
            'Mes' => ReportFilters::monthTitle(ReportFilters::month($filters['month'])),
            'Atrasadas' => $filters['include_overdue'] ? 'Incluye cuotas atrasadas de meses anteriores' : 'Sin cuotas atrasadas de meses anteriores',
            'Localidad' => $filters['city'],
        ]);
    }

    /**
     * @return array{
     *     groups: list<array{
     *         customer: Customer,
     *         lines: list<array{installment: Installment, days_late: int, balance: string, previous: bool}>,
     *         subtotal: string
     *     }>,
     *     count: int,
     *     month_amount: string,
     *     previous_amount: string,
     *     total: string
     * }
     */
    public function build(array $filters): array
    {
        $today = CarbonImmutable::today();
        $start = ReportFilters::month($filters['month']);
        $end = $start->endOfMonth();

        // "Atrasada de meses anteriores": ya venció y es anterior al mes elegido.
        $previousBefore = $start->lt($today) ? $start : $today;

        $installments = Installment::query()
            ->unpaid()
            ->where(function (Builder $query) use ($start, $end, $previousBefore, $filters): void {
                $query->where(fn (Builder $month) => $month
                    ->whereDate('due_date', '>=', $start->toDateString())
                    ->whereDate('due_date', '<=', $end->toDateString()));

                if ($filters['include_overdue']) {
                    $query->orWhereDate('due_date', '<', $previousBefore->toDateString());
                }
            })
            ->when($filters['city'] !== null, fn (Builder $query) => $query->whereHas(
                'customer',
                fn (Builder $customer) => $customer->where('city', $filters['city']),
            ))
            ->with(['customer', 'sale:id,installments_count'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $monthAmount = '0.00';
        $previousAmount = '0.00';

        $groups = $installments
            ->groupBy('customer_id')
            ->map(function (Collection $customerInstallments) use ($today, $start, &$monthAmount, &$previousAmount): array {
                $subtotal = '0.00';
                $lines = [];

                foreach ($customerInstallments as $installment) {
                    $balance = $installment->balance();
                    $previous = $installment->due_date->lt($start);

                    $subtotal = bcadd($subtotal, $balance, 2);

                    if ($previous) {
                        $previousAmount = bcadd($previousAmount, $balance, 2);
                    } else {
                        $monthAmount = bcadd($monthAmount, $balance, 2);
                    }

                    $lines[] = [
                        'installment' => $installment,
                        'days_late' => $installment->due_date->lt($today) ? (int) $installment->due_date->diffInDays($today) : 0,
                        'balance' => $balance,
                        'previous' => $previous,
                    ];
                }

                return ['customer' => $customerInstallments->first()->customer, 'lines' => $lines, 'subtotal' => $subtotal];
            })
            ->sortBy(fn (array $group): string => mb_strtolower($group['customer']->name))
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'count' => $installments->count(),
            'month_amount' => $monthAmount,
            'previous_amount' => $previousAmount,
            'total' => bcadd($monthAmount, $previousAmount, 2),
        ];
    }
}
