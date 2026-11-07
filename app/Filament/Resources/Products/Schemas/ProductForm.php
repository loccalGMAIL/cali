<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\PriceMode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producto')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sku')->label('Código')->unique(ignoreRecord: true)->maxLength(50),
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                        TextInput::make('brand')->label('Marca')->maxLength(255),
                        Toggle::make('active')->label('Activo')->default(true)->inline(false),
                        Textarea::make('description')->label('Descripción')->columnSpanFull(),
                    ]),
                Section::make('Stock')
                    ->description('El stock se modifica con compras, ventas y ajustes; acá solo se define el mínimo.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('stock')->label('Stock actual')->integer()->default(0)
                            ->disabled()->dehydrated(false),
                        TextInput::make('min_stock')->label('Stock mínimo')->integer()->minValue(0)->default(0)
                            ->dehydrateStateUsing(fn ($state): int => (int) $state),
                    ]),
                Section::make('Precio')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('last_cost')->label('Último costo')->required()->numeric()->minValue(0)
                            ->default(0)->prefix('$'),
                        TextInput::make('margin_percent')->label('Margen')->required()->numeric()->minValue(0)
                            ->default(0)->suffix('%'),
                        Select::make('price_mode')->label('Modo de precio')->options(PriceMode::class)
                            ->default(PriceMode::Auto)->required()->live(),
                        TextInput::make('sale_price')->label('Precio de venta')->numeric()->minValue(0)
                            ->default(0)->prefix('$')
                            ->required(fn (Get $get): bool => self::isManual($get('price_mode')))
                            ->disabled(fn (Get $get): bool => ! self::isManual($get('price_mode')))
                            ->dehydrated(fn (Get $get): bool => self::isManual($get('price_mode')))
                            ->helperText('En modo automático se calcula como costo + margen al guardar.'),
                    ]),
            ]);
    }

    private static function isManual(PriceMode|string|null $mode): bool
    {
        return ($mode instanceof PriceMode ? $mode : PriceMode::tryFrom((string) $mode)) === PriceMode::Manual;
    }
}
