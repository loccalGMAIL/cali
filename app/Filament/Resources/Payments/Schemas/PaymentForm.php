<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Installment;
use App\Support\Money;
use App\Support\PaymentPreview;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Formulario de cobro, en el orden en que se piensa: primero el cliente, después su deuda con las
 * cuotas para elegir cuáles se pagan, y recién entonces el monto, el medio de pago y la fecha.
 */
class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('customer_id')->label('Cliente')
                            ->options(fn (): array => Customer::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()->live()
                            ->default(fn () => request()->integer('customer') ?: null)
                            ->afterStateUpdated(function (Set $set): void {
                                // Al cambiar de cliente, la selección y el monto del anterior ya no valen.
                                $set('installment_ids', []);
                                $set('amount', null);
                            }),
                    ]),
                Section::make('Deuda del cliente')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('debt_preview')->hiddenLabel()
                            ->state(fn (Get $get) => PaymentPreview::debt($get('customer_id')))
                            ->html(),
                        CheckboxList::make('installment_ids')->label('Cuotas a imputar')
                            ->helperText('Elegí las cuotas que se pagan y el monto se calcula solo. Si no elegís ninguna, el cobro se imputa a la deuda desde la cuota más vieja.')
                            ->options(fn (Get $get): array => PaymentPreview::options($get('customer_id')))
                            ->descriptions(fn (Get $get): array => PaymentPreview::descriptions($get('customer_id')))
                            ->bulkToggleable()
                            ->columns(1)
                            ->live()
                            ->default(fn (): array => request()->filled('installment') ? [(string) request()->integer('installment')] : [])
                            ->hidden(fn (Get $get): bool => PaymentPreview::options($get('customer_id')) === [])
                            ->afterStateUpdated(fn (Get $get, Set $set, ?array $state) => self::syncAmount($get, $set, $state ?? []))
                            ->hintAction(fn (): Action => Action::make('selectOverdue')
                                ->label('Elegir las vencidas')
                                ->action(function (Get $get, Set $set): void {
                                    $ids = PaymentPreview::overdueIds($get('customer_id'));
                                    $set('installment_ids', $ids);
                                    self::syncAmount($get, $set, $ids);
                                })),
                    ]),
                Section::make('Cobro')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('amount')->label('Monto')->required()->numeric()->minValue(0.01)->prefix('$')->live(onBlur: true)
                            ->default(function (): ?string {
                                $installment = request()->filled('installment') ? Installment::find(request()->integer('installment')) : null;

                                return $installment?->balance();
                            })
                            ->maxValue(fn (Get $get): ?float => filled($get('installment_ids'))
                                ? (float) PaymentPreview::total($get('customer_id'), $get('installment_ids'))
                                : null)
                            ->helperText(fn (Get $get): ?string => filled($get('installment_ids'))
                                ? 'Total de las cuotas elegidas: '.Money::format(PaymentPreview::total($get('customer_id'), $get('installment_ids'))).'. Podés cobrar menos para un pago parcial.'
                                : null),
                        Select::make('method')->label('Medio de pago')->options(PaymentMethod::class)
                            ->default(PaymentMethod::Cash)->required(),
                        DatePicker::make('date')->label('Fecha')->default(now())->required(),
                    ]),
                Section::make('Imputación del cobro')
                    ->description('Se aplica a las cuotas elegidas, o a la deuda desde la cuota más vieja si no elegiste ninguna.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('allocation_preview')->hiddenLabel()
                            ->state(fn (Get $get) => PaymentPreview::allocation($get('customer_id'), $get('amount'), $get('installment_ids') ?? []))
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

    /**
     * El monto pasa a ser el total de las cuotas elegidas; si no queda ninguna, se vacía.
     *
     * @param  array<int|string>  $selected
     */
    private static function syncAmount(Get $get, Set $set, array $selected): void
    {
        $set('amount', $selected === [] ? null : PaymentPreview::total($get('customer_id'), $selected));
    }
}
