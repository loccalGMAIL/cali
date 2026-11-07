<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
                        Select::make('supplier_id')->label('Proveedor')
                            ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()->live()
                            ->createOptionForm(fn (Schema $schema): Schema => SupplierForm::configure($schema))
                            ->createOptionUsing(fn (array $data): int => Supplier::create($data)->getKey())
                            ->createOptionModalHeading('Nuevo proveedor')
                            ->createOptionAction(fn (Action $action): Action => $action->modalWidth('3xl')),
                        TextInput::make('invoice_number')->label('Número de factura')->required()->maxLength(50)
                            ->unique(
                                table: 'purchases',
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('supplier_id', $get('supplier_id')),
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
                Section::make('Ítems')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')->label('Productos comprados')
                            ->addActionLabel('Agregar producto')
                            ->minItems(1)->required()->live()->columns(4)
                            ->defaultItems(1)
                            ->schema([
                                Select::make('product_id')->label('Producto')
                                    ->options(fn (): array => Product::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()->required()->live()->columnSpan(2)
                                    ->createOptionForm(fn (Schema $schema): Schema => ProductForm::configure($schema))
                                    ->createOptionUsing(fn (array $data): int => Product::create($data)->getKey())
                                    ->createOptionModalHeading('Nuevo producto')
                                    ->createOptionAction(fn (Action $action): Action => $action->modalWidth('4xl'))
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if ($state !== null && ($product = Product::find($state))) {
                                            $set('unit_cost', $product->last_cost);
                                        }
                                    }),
                                TextInput::make('quantity')->label('Cantidad')->required()->integer()->minValue(1)->default(1)->live(onBlur: true),
                                TextInput::make('unit_cost')->label('Costo unitario')->required()->numeric()->minValue(0)->prefix('$')->live(onBlur: true),
                            ]),
                        TextEntry::make('total_preview')->label('Total')
                            ->state(fn (Get $get): string => Money::format(self::total($get('items') ?? [])))
                            ->weight('bold')->size('lg'),
                    ]),
            ]);
    }

    /**
     * @param  array<array-key, array{quantity?: mixed, unit_cost?: mixed}>  $items
     */
    private static function total(array $items): float
    {
        return collect($items)->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));
    }
}
