<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\User;
use App\Services\SaleCalculator;
use App\Support\Money;
use App\Support\SalePreview;
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

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Venta')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('date')->label('Fecha de la venta')->default(now())->required(),
                        Select::make('customer_id')->label('Cliente')
                            ->options(fn (): array => Customer::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()
                            ->createOptionForm(fn (Schema $schema): Schema => CustomerForm::configure($schema))
                            ->createOptionUsing(fn (array $data): int => Customer::create($data)->getKey())
                            ->createOptionModalHeading('Nuevo cliente')
                            ->createOptionAction(fn (Action $action): Action => $action->modalWidth('3xl')),
                        Select::make('seller_id')->label('Vendedor')
                            ->options(fn (): array => User::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => self::defaultSellerId())
                            ->required()->searchable()
                            ->disabled(fn (): bool => (bool) auth()->user()?->isSeller())
                            ->dehydrated(),
                    ]),
                Section::make('Productos')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')->label('Ítems')
                            ->addActionLabel('Agregar producto')
                            ->minItems(1)->required()->live()->columns(7)->defaultItems(1)
                            ->schema([
                                Select::make('product_id')->label('Producto')
                                    ->options(fn (): array => Product::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()->required()->live()->columnSpan(2)
                                    ->createOptionForm(fn (Schema $schema): Schema => ProductForm::configure($schema))
                                    ->createOptionUsing(fn (array $data): int => Product::create($data)->getKey())
                                    ->createOptionModalHeading('Nuevo producto')
                                    ->createOptionAction(fn (Action $action): Action => $action
                                        ->modalWidth('4xl')
                                        ->visible(fn (): bool => ProductResource::canCreate()))
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        // Se sugieren el costo de la última factura de compra y el flete del producto; se pueden cambiar.
                                        if ($state !== null && ($product = Product::find($state))) {
                                            $set('unit_cost', $product->last_cost);
                                            $set('unit_freight', $product->freight);
                                        }
                                    }),
                                TextInput::make('quantity')->label('Cantidad')->required()->integer()->minValue(1)->default(1)->live(onBlur: true),
                                TextInput::make('unit_cost')->label('Costo')->required()->numeric()->minValue(0)->prefix('$')->live(onBlur: true),
                                TextInput::make('unit_freight')->label('Flete')->numeric()->minValue(0)->default(0)->prefix('$')->live(onBlur: true)
                                    ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : $state),
                                TextInput::make('unit_margin')->label('Margen')->required()->numeric()->minValue(0)->default(0)->prefix('$')->live(onBlur: true),
                                TextEntry::make('unit_price')->label('Precio')
                                    ->state(fn (Get $get): string => Money::format(self::unitPrice($get('unit_cost'), $get('unit_margin'), $get('unit_freight'))))
                                    ->weight('bold'),
                            ]),
                    ]),
                Section::make('Pago')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('financing_plan_id')->label('Plan de financiación')
                            ->options(fn (): array => FinancingPlan::query()->active()->pluck('name', 'id')->all())
                            ->required()->live(),
                        Select::make('first_due_date')->label('Vencimiento de la primera cuota')
                            ->options(fn (): array => self::firstDueDateOptions())
                            ->default(SaleCalculator::dueDateFor(today(), 1)->toDateString())
                            ->helperText('Todas las cuotas vencen el día 30 de cada mes (en febrero, el último día).')
                            ->required()->live(),
                        TextInput::make('down_payment')->label('Anticipo')->numeric()->minValue(0)->default(0)->prefix('$')->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : $state),
                        Select::make('down_payment_method')->label('Medio de pago del anticipo')
                            ->options(PaymentMethod::class)->default(PaymentMethod::Cash)->required(),
                    ]),
                Section::make('Resumen')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('preview')->hiddenLabel()
                            ->state(fn (Get $get) => SalePreview::render(
                                $get('items') ?? [],
                                $get('financing_plan_id'),
                                $get('first_due_date'),
                                $get('down_payment'),
                            ))
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
     * El vendedor carga a su nombre; el resto arranca con el vendedor por defecto (o con quien carga).
     */
    /**
     * Vencimientos posibles para la primera cuota: el día 30 de cada uno de los próximos 24 meses.
     *
     * @return array<string, string>
     */
    private static function firstDueDateOptions(): array
    {
        $options = [];

        for ($offset = 0; $offset < 24; $offset++) {
            $dueDate = SaleCalculator::dueDateFor(today(), $offset);
            $options[$dueDate->toDateString()] = $dueDate->format('d/m/Y').' ('.ucfirst($dueDate->locale('es')->translatedFormat('F Y')).')';
        }

        return $options;
    }

    private static function defaultSellerId(): ?int
    {
        $user = auth()->user();

        if ($user?->isSeller()) {
            return $user->getKey();
        }

        return User::defaultSeller()?->getKey() ?? $user?->getKey();
    }

    private static function unitPrice(mixed $unitCost, mixed $unitMargin, mixed $unitFreight): string
    {
        return (new SaleCalculator)->unitPrice(
            is_numeric($unitCost) ? (string) $unitCost : '0',
            is_numeric($unitMargin) ? (string) $unitMargin : '0',
            is_numeric($unitFreight) ? (string) $unitFreight : '0',
        );
    }
}
