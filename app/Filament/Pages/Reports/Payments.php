<?php

namespace App\Filament\Pages\Reports;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\PaymentsReport;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class Payments extends ReportPage
{
    protected static ?string $slug = 'reports/payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 3;

    protected static function reportClass(): string
    {
        return PaymentsReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            DatePicker::make('from')->label('Desde')->live(),
            DatePicker::make('to')->label('Hasta')->live(),
            Select::make('method')->label('Medio de pago')
                ->options(PaymentMethod::class)
                ->placeholder('Todos')
                ->live(),
            Select::make('collected_by')->label('Cobró')
                ->options(fn (): array => User::query()
                    ->whereIn('id', Payment::query()->select('collected_by'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Todos')
                ->live(),
        ];
    }

    protected function reportColumns(): array
    {
        return [
            TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
            TextColumn::make('id')->label('Cobro')->formatStateUsing(fn ($state): string => Payment::numberFor($state))->sortable(),
            TextColumn::make('customer')->label('Cliente')->searchable()->sortable()
                ->url(fn (array $record): ?string => $record['customer_id'] === null
                    ? null
                    : CustomerResource::getUrl('view', ['record' => $record['customer_id']])),
            TextColumn::make('concept')->label('Concepto')->searchable()->sortable(),
            TextColumn::make('products')->label('Producto')->searchable()->sortable(),
            TextColumn::make('installment')->label('Cuota')->searchable()->sortable(),
            TextColumn::make('method')->label('Medio')->badge()->searchable()->sortable(),
            TextColumn::make('collector')->label('Cobró')->searchable()->sortable(),
            TextColumn::make('amount')->label('Monto')->formatStateUsing(fn (float $state): string => Money::format($state))->alignEnd()->sortable(),
        ];
    }

    protected function tableRows(array $report): array
    {
        $rows = [];

        foreach ($report['payments'] as $payment) {
            $rows[$payment->getKey()] = [
                'date' => $payment->date->toDateString(),
                'id' => $payment->getKey(),
                'customer_id' => $payment->customer_id,
                'customer' => $payment->customer->name ?? '—',
                'concept' => $payment->isDownPayment() ? "Anticipo venta {$payment->sale->number()}" : 'Cobro de cuotas',
                'products' => $payment->productsLabel(),
                'installment' => $payment->installmentsLabel(),
                'method' => $payment->method->getLabel(),
                'collector' => $payment->collector->name ?? '—',
                'amount' => (float) $payment->amount,
            ];
        }

        return $rows;
    }

    protected function searchKeys(): array
    {
        return ['customer', 'concept', 'products', 'installment', 'method', 'collector'];
    }

    protected function emptyMessage(): string
    {
        return 'No hay cobros en el período';
    }
}
