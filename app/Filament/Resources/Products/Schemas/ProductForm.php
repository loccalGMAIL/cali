<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producto')
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sku')->label('Código')->unique(ignoreRecord: true)->maxLength(50)
                            ->columnSpan(['default' => 12, 'md' => 2]),
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 4]),
                        TextInput::make('brand')->label('Marca')->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 4]),
                        Toggle::make('active')->label('Activo')->default(true)->inline(false)
                            ->columnSpan(['default' => 12, 'md' => 2]),
                        Select::make('supplier_id')->label('Proveedor habitual')
                            ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->helperText('Sirve para armar la orden de compra de cada proveedor.')
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        TextInput::make('last_cost')->label('Último costo')->prefix('$')
                            ->disabled()->dehydrated(false)
                            ->helperText('Sale de la última factura de compra y se sugiere al vender.')
                            ->columnSpan(['default' => 12, 'md' => 3]),
                        TextInput::make('freight')->label('Flete')->prefix('$')
                            ->numeric()->minValue(0)->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : $state)
                            ->helperText('Opcional, por unidad. Se suma al precio de venta y se divide en las cuotas.')
                            ->columnSpan(['default' => 12, 'md' => 3]),
                        Textarea::make('description')->label('Descripción')->columnSpanFull(),
                    ]),
            ]);
    }
}
