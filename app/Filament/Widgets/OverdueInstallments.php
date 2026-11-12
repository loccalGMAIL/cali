<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Installment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueInstallments extends TableWidget
{
    use PersistsTableColumnsPerUser;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cuotas vencidas')
            ->query(fn (): Builder => Installment::query()->overdue()->with('customer'))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('customer.name')->label('Cliente')->searchable()
                    ->url(fn (Installment $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                TextColumn::make('sale_id')->label('Venta')->prefix('#'),
                TextColumn::make('number')->label('Cuota'),
                TextColumn::make('due_date')->label('Vencimiento')->date('d/m/Y')->sortable(),
                TextColumn::make('days_late')->label('Días de atraso')
                    ->state(fn (Installment $record): int => (int) $record->due_date->diffInDays(today()))
                    ->color('danger')->weight('bold'),
                TextColumn::make('balance')->label('Saldo')->money('ARS', locale: 'es_AR')
                    ->state(fn (Installment $record): string => $record->balance()),
            ])
            ->recordActions([
                Action::make('collect')->label('Cobrar')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->url(fn (Installment $record): string => PaymentResource::getUrl('create', ['customer' => $record->customer_id, 'installment' => $record->getKey()]))
                    ->visible(fn (): bool => PaymentResource::canCreate()),
            ])
            ->emptyStateHeading('No hay cuotas vencidas')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
