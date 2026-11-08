<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Números del dashboard. Todo excluye lo anulado: las cuotas anuladas no cuentan como deuda
 * (scope `unpaid`) y los cobros y compras anulados quedan fuera con `notCancelled`.
 *
 * Los montos viajan como strings decimales con 2 decimales.
 */
class DashboardMetrics
{
    /**
     * Ganancia que queda por cobrar de una cuota: lo pendiente × la parte del monto que no es costo.
     * (monto - pagado) × (1 - costo / monto) = (monto - pagado) × (monto - costo) / monto
     */
    private const FUTURE_PROFIT_SQL = 'COALESCE(SUM((amount - paid_amount) * (amount - cost_portion) / NULLIF(amount, 0)), 0)';

    /**
     * Total por cobrar: saldo de todas las cuotas no pagadas.
     *
     * @return array{amount: string, count: int}
     */
    public function receivable(): array
    {
        return $this->installmentTotals(Installment::query()->unpaid());
    }

    /**
     * Cuotas vencidas: saldo y cantidad.
     *
     * @return array{amount: string, count: int}
     */
    public function overdue(): array
    {
        return $this->installmentTotals(Installment::query()->overdue());
    }

    /**
     * Ganancia futura: suma de la ganancia proporcional del saldo pendiente de cada cuota.
     */
    public function futureProfit(): string
    {
        return $this->decimal(Installment::query()->unpaid()->selectRaw(self::FUTURE_PROFIT_SQL.' as profit')->value('profit'));
    }

    /**
     * Cobrado en un rango de fechas (incluye anticipos; excluye cobros anulados).
     */
    public function collectedBetween(CarbonInterface $from, CarbonInterface $to): string
    {
        return $this->decimal(
            Payment::query()->notCancelled()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount'),
        );
    }

    /**
     * Comprado en un rango de fechas (excluye compras anuladas).
     */
    public function purchasedBetween(CarbonInterface $from, CarbonInterface $to): string
    {
        return $this->decimal(
            Purchase::query()->notCancelled()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('total'),
        );
    }

    /**
     * Valor del stock a último costo: Σ stock × último costo.
     */
    public function stockValue(): string
    {
        return $this->decimal(
            Product::query()->where('stock', '>', 0)->selectRaw('COALESCE(SUM(stock * last_cost), 0) as value')->value('value'),
        );
    }

    public function lowStockCount(): int
    {
        return Product::query()->active()->lowStock()->count();
    }

    /**
     * Monto a cobrar y ganancia por mes, desde el mes actual y por la cantidad de meses indicada.
     * Lo vencido se suma al mes actual, porque es lo que hay que cobrar ahora.
     *
     * @return list<array{month: CarbonImmutable, amount: string, profit: string}>
     */
    public function projection(int $months = 12, ?CarbonInterface $today = null): array
    {
        $start = CarbonImmutable::parse($today ?? today())->startOfMonth();
        $end = $start->addMonths($months)->subDay()->endOfDay();

        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $buckets[$start->addMonths($i)->format('Y-m')] = ['amount' => '0.00', 'profit' => '0.00'];
        }

        Installment::query()
            ->unpaid()
            ->whereDate('due_date', '<=', $end->toDateString())
            ->select(['due_date', 'amount', 'paid_amount', 'cost_portion'])
            ->orderBy('due_date')
            ->cursor()
            ->each(function (Installment $installment) use (&$buckets, $start): void {
                $key = max($installment->due_date->copy()->startOfMonth(), $start)->format('Y-m');
                $balance = bcsub($installment->amount, $installment->paid_amount, 2);

                // balance × (amount - costo) / amount
                $profit = bccomp($installment->amount, '0', 2) === 0
                    ? '0.00'
                    : bcdiv(bcmul($balance, bcsub($installment->amount, $installment->cost_portion, 2), 6), $installment->amount, 2);

                $buckets[$key]['amount'] = bcadd($buckets[$key]['amount'], $balance, 2);
                $buckets[$key]['profit'] = bcadd($buckets[$key]['profit'], $profit, 2);
            });

        $result = [];

        foreach ($buckets as $key => $bucket) {
            $result[] = ['month' => CarbonImmutable::createFromFormat('Y-m-d', $key.'-01')->startOfDay(), ...$bucket];
        }

        return $result;
    }

    /**
     * @param  Builder<Installment>  $query
     * @return array{amount: string, count: int}
     */
    private function installmentTotals($query): array
    {
        $row = $query->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as amount, COUNT(*) as total')->first();

        return [
            'amount' => $this->decimal($row?->getAttribute('amount')),
            'count' => (int) ($row?->getAttribute('total') ?? 0),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
