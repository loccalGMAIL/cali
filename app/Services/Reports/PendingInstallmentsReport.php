<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Installment;
use Carbon\CarbonImmutable;

/**
 * Cuotas pendientes por cliente, en grilla: una fila por cliente con deuda y una columna por mes.
 *
 * Lo que ya venció va a la columna "Vencido" (aunque sea del mes en curso); lo que vence después
 * del último mes mostrado va a "Más adelante". Así la suma de la fila es toda la deuda del cliente.
 */
class PendingInstallmentsReport implements Report
{
    public const MONTH_OPTIONS = [6, 12, 18, 24];

    public const OVERDUE = 'overdue';

    public const LATER = 'later';

    public static function slug(): string
    {
        return 'pending-installments';
    }

    public function title(): string
    {
        return 'Cuotas pendientes por cliente';
    }

    /**
     * @return array{months: int, only_overdue: bool, city: ?string}
     */
    public function normalize(array $input): array
    {
        $months = (int) ($input['months'] ?? 12);

        return [
            'months' => in_array($months, self::MONTH_OPTIONS, true) ? $months : 12,
            'only_overdue' => ReportFilters::boolean($input['only_overdue'] ?? null, false),
            'city' => ReportFilters::text($input['city'] ?? null),
        ];
    }

    public function describe(array $filters): array
    {
        return array_filter([
            'Meses' => (string) $filters['months'],
            'Clientes' => $filters['only_overdue'] ? 'Solo con cuotas vencidas' : null,
            'Localidad' => $filters['city'],
        ]);
    }

    /**
     * @return array{
     *     columns: array<string, string>,
     *     rows: list<array{customer: Customer, cells: array<string, string>, total: string}>,
     *     totals: array<string, string>,
     *     total: string
     * }
     */
    public function build(array $filters): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth();

        $columns = [self::OVERDUE => 'Vencido'];

        for ($i = 0; $i < $filters['months']; $i++) {
            $month = $start->addMonths($i);
            $columns[$month->format('Y-m')] = ReportFilters::monthLabel($month);
        }

        $columns[self::LATER] = 'Más adelante';
        $empty = array_fill_keys(array_keys($columns), '0.00');

        $cellsByCustomer = [];

        Installment::query()
            ->unpaid()
            ->when($filters['city'] !== null, fn ($query) => $query->whereIn(
                'customer_id',
                Customer::query()->where('city', $filters['city'])->select('id'),
            ))
            ->get(['id', 'customer_id', 'due_date', 'amount', 'paid_amount', 'status'])
            ->each(function (Installment $installment) use (&$cellsByCustomer, $empty, $today): void {
                $month = $installment->due_date->format('Y-m');

                $column = match (true) {
                    $installment->due_date->lt($today) => self::OVERDUE,
                    array_key_exists($month, $empty) => $month,
                    default => self::LATER,
                };

                $cells = $cellsByCustomer[$installment->customer_id] ?? $empty;
                $cells[$column] = bcadd($cells[$column], $installment->balance(), 2);
                $cellsByCustomer[$installment->customer_id] = $cells;
            });

        if ($filters['only_overdue']) {
            $cellsByCustomer = array_filter($cellsByCustomer, fn (array $cells): bool => bccomp($cells[self::OVERDUE], '0', 2) > 0);
        }

        $rows = [];
        $totals = $empty;

        Customer::query()->whereKey(array_keys($cellsByCustomer))->orderBy('name')->orderBy('id')->get()
            ->each(function (Customer $customer) use (&$rows, &$totals, $cellsByCustomer): void {
                $cells = $cellsByCustomer[$customer->getKey()];

                foreach ($cells as $column => $amount) {
                    $totals[$column] = bcadd($totals[$column], $amount, 2);
                }

                $rows[] = ['customer' => $customer, 'cells' => $cells, 'total' => $this->sum($cells)];
            });

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'total' => $this->sum($totals)];
    }

    /**
     * @param  array<string, string>  $amounts
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');
    }
}
