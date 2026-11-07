<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\PriceMode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                        Textarea::make('description')->label('Descripción')->columnSpanFull(),
                    ]),
                Grid::make(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Stock')
                            ->columns(2)
                            ->schema([
                                TextInput::make('stock')->label('Stock actual')->integer()->default(0)
                                    ->disabled()->dehydrated(false),
                                TextInput::make('min_stock')->label('Stock mínimo')->integer()->minValue(0)->default(0)
                                    ->dehydrateStateUsing(fn ($state): int => (int) $state),
                            ]),
                        Section::make('Precio')
                            ->columns(2)
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
                                    ->helperText('En modo automático se calcula al guardar.'),
                            ]),
                    ]),
            ]);
    }

    private static function isManual(PriceMode|string|null $mode): bool
    {
        return ($mode instanceof PriceMode ? $mode : PriceMode::tryFrom((string) $mode)) === PriceMode::Manual;
    }
}
