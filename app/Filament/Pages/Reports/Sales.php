<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\SalesReport;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class Sales extends ReportPage
{
    protected static ?string $slug = 'reports/sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 4;

    protected static function reportClass(): string
    {
        return SalesReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            DatePicker::make('from')->label('Desde')->live(),
            DatePicker::make('to')->label('Hasta')->live(),
            Select::make('seller_id')->label('Vendedor')
                ->options(fn (): array => User::query()
                    ->whereIn('id', Sale::query()->select('seller_id'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Todos')
                ->live(),
            Select::make('group_by')->label('Agrupar por')
                ->options(SalesReport::GROUP_OPTIONS)
                ->selectablePlaceholder(false)
                ->live(),
        ];
    }

    protected function reportColumns(): array
    {
        $isDetail = fn (): bool => $this->filters()['group_by'] === 'sale';
        $isGrouped = fn (): bool => $this->filters()['group_by'] !== 'sale';
        $money = fn (float $state): string => Money::format($state);

        return [
            TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable()->visible($isDetail),
            TextColumn::make('label')
                ->label(fn (): string => match ($this->filters()['group_by']) {
                    'product' => 'Producto',
                    'seller' => 'Vendedor',
                    default => 'Venta',
                })
                ->searchable()->sortable()
                ->url(fn (array $record): ?string => $record['sale_id'] === null ? null : SaleResource::getUrl('view', ['record' => $record['sale_id']])),
            TextColumn::make('customer')->label('Cliente')->searchable()->sortable()->visible($isDetail),
            TextColumn::make('seller')->label('Vendedor')->searchable()->sortable()->visible($isDetail),
            TextColumn::make('installments_count')->label('Cuotas')->alignEnd()->sortable()->visible($isDetail),
            TextColumn::make('down_payment')->label('Anticipo')->formatStateUsing($money)->alignEnd()->sortable()->visible($isDetail),
            TextColumn::make('quantity')
                ->label(fn (): string => $this->filters()['group_by'] === 'product' ? 'Unidades' : 'Ventas')
                ->alignEnd()->sortable()->visible($isGrouped),
            TextColumn::make('total')->label('Total')->formatStateUsing($money)->alignEnd()->sortable(),
            TextColumn::make('cost')->label('Costo')->formatStateUsing($money)->alignEnd()->sortable(),
            TextColumn::make('freight')->label('Flete')->formatStateUsing($money)->alignEnd()->sortable(),
            TextColumn::make('profit')->label('Ganancia')->formatStateUsing($money)->alignEnd()->sortable(),
            TextColumn::make('margin')->label('Margen')
                ->formatStateUsing(fn (float $state): string => number_format($state, 1, ',', '.').'%')
                ->alignEnd()->sortable(),
        ];
    }

    protected function tableRows(array $report): array
    {
        $rows = [];

        foreach ($report['rows'] as $index => $row) {
            $sale = $row['sale'] ?? null;

            $rows[$sale === null ? "group-{$index}" : "sale-{$sale->getKey()}"] = [
                'sale_id' => $sale?->getKey(),
                'label' => $row['label'],
                'date' => $sale?->date->toDateString(),
                'customer' => $sale === null ? null : ($sale->customer->name ?? '—'),
                'seller' => $sale === null ? null : ($sale->seller->name ?? '—'),
                'installments_count' => $sale?->installments_count,
                'down_payment' => $sale === null ? null : (float) $sale->down_payment,
                'quantity' => $row['quantity'],
                'total' => (float) $row['total'],
                'cost' => (float) $row['cost'],
                'freight' => (float) $row['freight'],
                'profit' => (float) $row['profit'],
                'margin' => (float) $row['margin'],
            ];
        }

        return $rows;
    }

    protected function searchKeys(): array
    {
        return ['label', 'customer', 'seller'];
    }

    protected function emptyMessage(): string
    {
        return 'No hay ventas en el período';
    }
}
