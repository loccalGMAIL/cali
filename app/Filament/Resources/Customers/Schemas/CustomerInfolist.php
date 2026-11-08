<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Services\AccountService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Situación de la cuenta')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('account_balance')->label('Saldo de cuenta corriente')
                            ->state(fn (Customer $record): string => app(AccountService::class)->balance($record))
                            ->money('ARS', locale: 'es_AR')->weight('bold')->size('lg'),
                        TextEntry::make('debt')->label('Deuda en cuotas')
                            ->state(fn (Customer $record): string => $record->debt())
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('overdue_debt')->label('Cuotas vencidas')
                            ->state(fn (Customer $record): string => $record->overdueDebt())
                            ->money('ARS', locale: 'es_AR')
                            ->color(fn (Customer $record): ?string => $record->overdueCount() > 0 ? 'danger' : null)
                            ->weight('bold')
                            ->helperText(fn (Customer $record): string => match ($count = $record->overdueCount()) {
                                0 => 'Sin cuotas vencidas',
                                1 => '1 cuota vencida',
                                default => "{$count} cuotas vencidas",
                            }),
                        TextEntry::make('next_due')->label('Próximo vencimiento')
                            ->state(fn (Customer $record): ?string => $record->nextDueDate()?->format('d/m/Y'))
                            ->placeholder('—'),
                    ]),
                Section::make('Datos del cliente')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')->label('Nombre y apellido'),
                        TextEntry::make('document_number')->label('DNI / CUIT')->placeholder('—'),
                        TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                        TextEntry::make('email')->label('Email')->placeholder('—'),
                        TextEntry::make('address')->label('Dirección')->placeholder('—'),
                        TextEntry::make('city')->label('Localidad')->placeholder('—'),
                        IconEntry::make('active')->label('Activo')->boolean(),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
