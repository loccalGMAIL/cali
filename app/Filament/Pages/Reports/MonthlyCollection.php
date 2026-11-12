<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Sale;
use App\Services\Reports\MonthlyCollectionReport;
use App\Services\Reports\ReportFilters;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MonthlyCollection extends ReportPage
{
    protected static ?string $slug = 'reports/monthly-collection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    protected static function reportClass(): string
    {
        return MonthlyCollectionReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            Select::make('month')->label('Mes')
                ->options(fn (): array => self::monthOptions())
                ->selectablePlaceholder(false)
                ->live(),
            $this->cityFilter(),
            Toggle::make('include_overdue')->label('Incluir atrasadas de meses anteriores')->inline(false)->live(),
        ];
    }

    protected function reportColumns(): array
    {
        $late = fn (array $record): ?string => $record['days_late'] > 0 ? 'danger' : null;

        return [
            TextColumn::make('customer')->label('Cliente')->searchable()->sortable()
                ->description(fn (array $record): ?string => $record['contact'] ?: null)
                ->url(fn (array $record): string => CustomerResource::getUrl('view', ['record' => $record['customer_id']])),
            TextColumn::make('sale_id')->label('Venta')->formatStateUsing(fn ($state): string => Sale::numberFor($state))->sortable(),
            TextColumn::make('installment')->label('Cuota')->sortable(),
            TextColumn::make('due_date')->label('Vencimiento')->date('d/m/Y')->sortable()->color($late),
            TextColumn::make('days_late')->label('Días de atraso')
                ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '–')
                ->alignEnd()->sortable()->color($late),
            TextColumn::make('balance')->label('Saldo')
                ->formatStateUsing(fn (float $state): string => Money::format($state))
                ->alignEnd()->sortable(),
        ];
    }

    protected function tableRows(array $report): array
    {
        $rows = [];

        foreach ($report['groups'] as $group) {
            $customer = $group['customer'];
            $contact = collect([
                $customer->phone ? "Tel. {$customer->phone}" : null,
                $customer->address,
                $customer->city,
            ])->filter()->implode(' · ');

            foreach ($group['lines'] as $line) {
                $installment = $line['installment'];

                $rows[$installment->getKey()] = [
                    'customer_id' => $customer->getKey(),
                    'customer' => $customer->name,
                    'contact' => $contact,
                    'sale_id' => $installment->sale_id,
                    'installment' => "{$installment->number}/{$installment->sale->installments_count}",
                    'due_date' => $installment->due_date->toDateString(),
                    'days_late' => $line['days_late'],
                    'balance' => (float) $line['balance'],
                ];
            }
        }

        return $rows;
    }

    protected function searchKeys(): array
    {
        return ['customer', 'contact'];
    }

    protected function emptyMessage(): string
    {
        return 'No hay cuotas para cobrar con estos filtros';
    }

    /**
     * Desde tres meses atrás hasta un año adelante.
     *
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(3);
        $options = [];

        for ($i = 0; $i < 16; $i++) {
            $month = $start->addMonths($i);
            $options[$month->format('Y-m')] = ReportFilters::monthTitle($month);
        }

        return $options;
    }
}
