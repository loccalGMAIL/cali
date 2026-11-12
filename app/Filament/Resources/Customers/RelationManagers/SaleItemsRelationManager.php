<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\SaleStatus;
use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Productos que compró el cliente, con la venta a la que pertenecen y sus pagos. El pagado, el
 * saldo y los pagos son de la venta entera: si una venta tiene varios productos se repiten.
 */
class SaleItemsRelationManager extends RelationManager
{
    use PersistsTableColumnsPerUser;

    protected static string $relationship = 'saleItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Productos comprados';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'product',
                'sale.downPayments',
                'sale.installments.payments',
            ]))
            ->columns([
                TextColumn::make('sale_id')->label('Venta')->formatStateUsing(fn ($state): string => Sale::numberFor($state))->sortable()
                    ->url(fn (SaleItem $record): string => SaleResource::getUrl('view', ['record' => $record->sale_id])),
                TextColumn::make('sale.date')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('product.name')->label('Producto')->searchable(),
                TextColumn::make('quantity')->label('Cant.')->alignEnd(),
                TextColumn::make('subtotal')->label('Importe')->money('ARS', locale: 'es_AR'),
                TextColumn::make('sale.status')->label('Estado')->badge(),
                TextColumn::make('paid')->label('Pagado')->alignEnd()
                    ->state(fn (SaleItem $record): string => Money::format($record->sale->paidAmount())),
                TextColumn::make('balance')->label('Saldo')->alignEnd()
                    ->state(fn (SaleItem $record): string => Money::format($record->sale->balanceAmount())),
                TextColumn::make('payments')->label('Pagos')
                    ->state(fn (SaleItem $record): array => $record->sale->paymentLines()
                        ->map(fn (array $line): string => "{$line['date']->format('d/m/Y')} · ".Money::format($line['amount'])." · {$line['concept']}")
                        ->all())
                    ->listWithLineBreaks()->limitList(3)->expandableLimitedList()
                    ->placeholder('Sin pagos'),
            ])
            ->defaultSort('sale_id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado de la venta')
                    ->options(SaleStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('sale', fn (Builder $sale): Builder => $sale->where('status', $data['value']))
                        : $query),
            ]);
    }
}
