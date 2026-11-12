<?php

namespace App\Services\Reports;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ventas de un período (sin las anuladas) con su ganancia, en detalle o agrupadas por vendedor
 * o por producto.
 *
 * El precio es costo + flete + margen, sin recargo por financiación: la ganancia de una venta es
 * total − costo − flete.
 */
class SalesReport implements Report
{
    public const GROUP_OPTIONS = ['sale' => 'Venta (detalle)', 'seller' => 'Vendedor', 'product' => 'Producto'];

    private const EMPTY_GROUP = ['quantity' => 0, 'total' => '0.00', 'cost' => '0.00', 'freight' => '0.00', 'profit' => '0.00'];

    public static function slug(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Ventas y ganancia';
    }

    /**
     * @return array{from: string, to: string, seller_id: ?int, group_by: string}
     */
    public function normalize(array $input): array
    {
        [$from, $to] = ReportFilters::dateRange($input);
        $groupBy = $input['group_by'] ?? null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'seller_id' => ReportFilters::integer($input['seller_id'] ?? null),
            'group_by' => is_string($groupBy) && array_key_exists($groupBy, self::GROUP_OPTIONS) ? $groupBy : 'sale',
        ];
    }

    public function describe(array $filters): array
    {
        return array_filter([
            'Período' => ReportFilters::period($filters['from'], $filters['to']),
            'Vendedor' => $filters['seller_id'] === null ? null : User::query()->whereKey($filters['seller_id'])->value('name'),
            'Agrupado por' => self::GROUP_OPTIONS[$filters['group_by']],
        ]);
    }

    /**
     * Cada fila trae `label`, `quantity` (ventas o unidades), `total`, `cost`, `freight`, `profit`
     * y `margin` (ganancia sobre el total, en %). En el detalle por venta trae además `sale`.
     *
     * @return array{
     *     group_by: string,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function build(array $filters): array
    {
        $rows = match ($filters['group_by']) {
            'product' => $this->byProduct($filters),
            'seller' => $this->bySeller($filters),
            default => $this->bySale($filters),
        };

        $totals = self::EMPTY_GROUP;

        foreach ($rows as $row) {
            $totals = $this->accumulate($totals, $row['quantity'], $row['total'], $row['cost'], $row['freight'], $row['profit']);
        }

        return [
            'group_by' => $filters['group_by'],
            'rows' => $rows,
            'totals' => [...$totals, 'margin' => $this->margin($totals['profit'], $totals['total'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    private function sales(array $filters): Builder
    {
        return Sale::query()
            ->notCancelled()
            ->whereDate('date', '>=', $filters['from'])
            ->whereDate('date', '<=', $filters['to'])
            ->when($filters['seller_id'] !== null, fn (Builder $query) => $query->where('seller_id', $filters['seller_id']));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function bySale(array $filters): array
    {
        return $this->sales($filters)
            ->with(['customer:id,name', 'seller:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (Sale $sale): array {
                $profit = $this->saleProfit($sale);

                return [
                    'label' => $sale->number(),
                    'sale' => $sale,
                    'quantity' => 1,
                    'total' => $sale->total,
                    'cost' => $sale->total_cost,
                    'freight' => $sale->total_freight,
                    'profit' => $profit,
                    'margin' => $this->margin($profit, $sale->total),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function bySeller(array $filters): array
    {
        $groups = [];

        foreach ($this->sales($filters)->with('seller:id,name')->get() as $sale) {
            $key = (int) $sale->seller_id;
            $group = $groups[$key] ?? ['label' => $sale->seller->name ?? '—', ...self::EMPTY_GROUP];

            $groups[$key] = $this->accumulate($group, 1, $sale->total, $sale->total_cost, $sale->total_freight, $this->saleProfit($sale));
        }

        return $this->groupRows($groups);
    }

    /**
     * Por producto se calcula desde los ítems: cantidad × costo, flete y margen unitarios.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function byProduct(array $filters): array
    {
        $groups = [];

        $items = SaleItem::query()
            ->whereIn('sale_id', $this->sales($filters)->select('id'))
            ->with('product:id,name')
            ->get();

        foreach ($items as $item) {
            $key = (int) $item->product_id;
            $quantity = (string) $item->quantity;
            $group = $groups[$key] ?? ['label' => $item->product->name ?? '—', ...self::EMPTY_GROUP];

            $groups[$key] = $this->accumulate(
                $group,
                $item->quantity,
                $item->subtotal,
                bcmul($quantity, $item->unit_cost, 2),
                bcmul($quantity, $item->unit_freight, 2),
                bcmul($quantity, $item->unit_margin, 2),
            );
        }

        return $this->groupRows($groups);
    }

    /**
     * Filas de un agrupado, ordenadas por nombre.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function groupRows(array $groups): array
    {
        usort($groups, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return array_map(
            fn (array $group): array => [...$group, 'margin' => $this->margin($group['profit'], $group['total'])],
            $groups,
        );
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function accumulate(array $group, int $quantity, string $total, string $cost, string $freight, string $profit): array
    {
        return [
            ...$group,
            'quantity' => $group['quantity'] + $quantity,
            'total' => bcadd($group['total'], $total, 2),
            'cost' => bcadd($group['cost'], $cost, 2),
            'freight' => bcadd($group['freight'], $freight, 2),
            'profit' => bcadd($group['profit'], $profit, 2),
        ];
    }

    private function saleProfit(Sale $sale): string
    {
        return bcsub(bcsub($sale->total, $sale->total_cost, 2), $sale->total_freight, 2);
    }

    /**
     * Ganancia sobre el total, en porcentaje con un decimal.
     */
    private function margin(string $profit, string $total): string
    {
        return bccomp($total, '0', 2) === 0 ? '0.0' : bcdiv(bcmul($profit, '100', 4), $total, 1);
    }
}
