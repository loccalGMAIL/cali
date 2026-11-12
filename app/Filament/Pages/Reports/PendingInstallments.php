<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\Reports\PendingInstallmentsReport;
use App\Services\Reports\ReportFilters;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class PendingInstallments extends ReportPage
{
    protected static ?string $slug = 'reports/pending-installments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 1;

    protected static function reportClass(): string
    {
        return PendingInstallmentsReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            Select::make('months')->label('Meses a mostrar')
                ->options(array_combine(
                    PendingInstallmentsReport::MONTH_OPTIONS,
                    array_map(fn (int $months): string => "{$months} meses", PendingInstallmentsReport::MONTH_OPTIONS),
                ))
                ->selectablePlaceholder(false)
                ->live(),
            $this->cityFilter(),
            Toggle::make('only_overdue')->label('Solo clientes con cuotas vencidas')->inline(false)->live(),
        ];
    }

    /**
     * Vencido, un mes por columna (hasta el máximo; se muestran los que pide el filtro), "Más
     * adelante" y el total de la fila.
     */
    protected function reportColumns(): array
    {
        $amount = fn (string $name, string $label): TextColumn => TextColumn::make($name)->label($label)
            ->formatStateUsing(fn (float $state): string => Money::cell($state))
            ->alignEnd()->sortable();

        $columns = [
            TextColumn::make('customer')->label('Cliente')->searchable()->sortable()
                ->url(fn (array $record): string => CustomerResource::getUrl('view', ['record' => $record['customer_id']])),
            $amount('overdue', 'Vencido')->color(fn (float $state): ?string => $state > 0 ? 'danger' : null),
        ];

        $start = CarbonImmutable::today()->startOfMonth();

        foreach (range(0, max(PendingInstallmentsReport::MONTH_OPTIONS) - 1) as $slot) {
            $columns[] = $amount("month_{$slot}", ReportFilters::monthLabel($start->addMonths($slot)))
                ->visible(fn (): bool => $slot < $this->filters()['months']);
        }

        $columns[] = $amount('later', 'Más adelante');
        $columns[] = $amount('total', 'Total')->weight('bold');

        return $columns;
    }

    protected function tableRows(array $report): array
    {
        $slots = [PendingInstallmentsReport::OVERDUE => 'overdue', PendingInstallmentsReport::LATER => 'later'];
        $slot = 0;

        foreach (array_keys($report['columns']) as $key) {
            if (! isset($slots[$key])) {
                $slots[$key] = 'month_'.$slot++;
            }
        }

        $rows = [];

        foreach ($report['rows'] as $row) {
            $values = [];

            foreach ($row['cells'] as $key => $amount) {
                $values[$slots[$key]] = (float) $amount;
            }

            $rows[$row['customer']->getKey()] = [
                'customer_id' => $row['customer']->getKey(),
                'customer' => $row['customer']->name,
                ...$values,
                'total' => (float) $row['total'],
            ];
        }

        return $rows;
    }

    protected function searchKeys(): array
    {
        return ['customer'];
    }

    protected function emptyMessage(): string
    {
        return 'No hay cuotas pendientes con estos filtros';
    }
}
