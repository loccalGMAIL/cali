<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Support\PaymentPreview;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cobro')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('customer_id')->label('Cliente')
                            ->options(fn (): array => Customer::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()->live()
                            ->default(fn () => request()->integer('customer') ?: null),
                        TextInput::make('amount')->label('Monto')->required()->numeric()->minValue(0.01)->prefix('$')->live(onBlur: true),
                        Select::make('method')->label('Medio de pago')->options(PaymentMethod::class)
                            ->default(PaymentMethod::Cash)->required(),
                        DatePicker::make('date')->label('Fecha')->default(now())->required(),
                    ]),
                Section::make('Deuda del cliente')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('debt_preview')->hiddenLabel()
                            ->state(fn (Get $get) => PaymentPreview::debt($get('customer_id')))
                            ->html(),
                    ]),
                Section::make('Imputación del cobro')
                    ->description('Se aplica a las cuotas con vencimiento más viejo primero.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('allocation_preview')->hiddenLabel()
                            ->state(fn (Get $get) => PaymentPreview::allocation($get('customer_id'), $get('amount')))
                            ->html(),
                    ]),
                Section::make('Notas')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')->label('Notas')->hiddenLabel(),
                    ]),
            ]);
    }
}
