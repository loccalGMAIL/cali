<?php

namespace App\Filament\Resources\PendingPurchases\Tables;

use App\Actions\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\SaleItem;
use App\Models\Supplier;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PendingPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['product.supplier', 'sale.customer']))
            ->columns([
                TextColumn::make('product.name')->label('Producto')->searchable()->sortable(),
                TextColumn::make('product.supplier.name')->label('Proveedor habitual')->placeholder('—'),
                TextColumn::make('pending_quantity')->label('Pendiente')->numeric()->weight('bold'),
                TextColumn::make('unit_cost')->label('Costo unitario')->money('ARS', locale: 'es_AR'),
                TextColumn::make('sale_id')->label('Venta')->prefix('#')->sortable()
                    ->url(fn (SaleItem $record): string => SaleResource::getUrl('view', ['record' => $record->sale_id])),
                TextColumn::make('sale.customer.name')->label('Cliente')->searchable(),
                TextColumn::make('sale.date')->label('Fecha de venta')->date('d/m/Y'),
            ])
            ->defaultSort('sale_id')
            ->filters([
                SelectFilter::make('supplier')->label('Proveedor habitual')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('product', fn (Builder $product): Builder => $product->where('supplier_id', $data['value']))),
            ])
            ->toolbarActions([
                BulkAction::make('createOrder')->label('Armar orden de compra')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->modalHeading('Armar orden de compra')
                    ->modalDescription('Se crea una orden en borrador con todo lo pendiente de los ítems elegidos. Después se puede editar.')
                    ->modalSubmitActionLabel('Armar orden')
                    ->schema([
                        Select::make('supplier_id')->label('Proveedor')
                            ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (?Collection $records): ?int => self::commonSupplier($records))
                            ->searchable()->required(),
                    ])
                    ->visible(fn (): bool => PurchaseOrderResource::canCreate())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        try {
                            $order = app(CreatePurchaseOrder::class)->handle(
                                ['supplier_id' => $data['supplier_id'], 'date' => today()->toDateString()],
                                $records->map(fn (SaleItem $item): array => ['sale_item_id' => $item->getKey(), 'quantity' => $item->pending_quantity])->values()->all(),
                                auth()->user(),
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title('No se pudo armar la orden')
                                ->body(collect($exception->errors())->flatten()->implode(' '))->send();

                            return;
                        }

                        Notification::make()->success()->title("Orden de compra #{$order->getKey()} armada")->send();

                        redirect(PurchaseOrderResource::getUrl('view', ['record' => $order]));
                    }),
            ]);
    }

    /**
     * Si todos los ítems elegidos tienen el mismo proveedor habitual, se propone ese.
     *
     * @param  Collection<int, SaleItem>|null  $records
     */
    private static function commonSupplier(?Collection $records): ?int
    {
        $suppliers = $records?->map(fn (SaleItem $item): ?int => $item->product?->supplier_id)->unique()->values();

        return $suppliers !== null && $suppliers->count() === 1 ? $suppliers->first() : null;
    }
}
