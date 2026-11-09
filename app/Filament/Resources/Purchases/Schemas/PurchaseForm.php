<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\PurchaseOrder;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factura')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('purchase_order_id')->label('Orden de compra')
                            ->options(fn (): array => PurchaseOrder::query()->open()->with('supplier')->orderByDesc('id')->get()
                                ->mapWithKeys(fn (PurchaseOrder $order): array => [
                                    $order->id => "#{$order->id} — {$order->supplier->name} ({$order->date->format('d/m/Y')}, {$order->status->getLabel()})",
                                ])->all())
                            ->searchable()->required()->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('items', self::itemsFor($state))),
                        TextInput::make('invoice_number')->label('Número de factura')->required()->maxLength(50)
                            ->unique(
                                table: 'purchases',
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('supplier_id', PurchaseOrder::query()->whereKey($get('purchase_order_id'))->value('supplier_id'))
                                    ->whereNull('cancelled_at'),
                                ignoreRecord: true,
                            )
                            ->validationMessages(['unique' => 'Ya existe una compra con ese número de factura para este proveedor.']),
                        DatePicker::make('date')->label('Fecha')->default(now())->required(),
                    ]),
                Section::make('Notas')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')->label('Notas')->hiddenLabel(),
                    ]),
                Section::make('Lo que llegó')
                    ->description('Corregí la cantidad si llegó menos de lo pedido: el faltante vuelve a pendientes de compra. El costo queda como último costo del producto.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('purchase_order_id')))
                    ->schema([
                        Repeater::make('items')->label('Productos')
                            ->addable(false)->deletable(false)->reorderable(false)
                            ->live()->columns(4)
                            ->schema([
                                Hidden::make('product_id'),
                                TextInput::make('product_name')->label('Producto')->disabled()->dehydrated(false),
                                TextInput::make('ordered_quantity')->label('Pedido')->disabled()->dehydrated(false),
                                TextInput::make('quantity')->label('Recibido')->required()->integer()->minValue(0)
                                    ->maxValue(fn (Get $get): int => (int) $get('ordered_quantity'))
                                    ->live(onBlur: true),
                                TextInput::make('unit_cost')->label('Costo unitario')->required()->numeric()->minValue(0)->prefix('$')->live(onBlur: true),
                            ]),
                        TextEntry::make('total_preview')->label('Total')
                            ->state(fn (Get $get): string => Money::format(self::total($get('items') ?? [])))
                            ->weight('bold')->size('lg'),
                    ]),
            ]);
    }

    /**
     * Ítems de la factura precargados con lo pedido en la orden: todo recibido, al costo de referencia.
     *
     * @return array<string, array{product_id: int, product_name: string, ordered_quantity: int, quantity: int, unit_cost: string}>
     */
    public static function itemsFor(mixed $orderId): array
    {
        $order = blank($orderId) ? null : PurchaseOrder::query()->find($orderId);

        if ($order === null) {
            return [];
        }

        return $order->linesByProduct()
            ->mapWithKeys(fn (array $line): array => [
                (string) str()->uuid() => [
                    'product_id' => $line['product']->getKey(),
                    'product_name' => $line['product']->name,
                    'ordered_quantity' => $line['quantity'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['reference_cost'],
                ],
            ])
            ->all();
    }

    /**
     * @param  array<array-key, array{quantity?: mixed, unit_cost?: mixed}>  $items
     */
    private static function total(array $items): float
    {
        return collect($items)->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));
    }
}
