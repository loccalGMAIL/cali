<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\User;
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
                        Select::make('customer_id')->label('Cliente')
                            ->options(fn (): array => Customer::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()
                            ->createOptionForm(fn (Schema $schema): Schema => CustomerForm::configure($schema))
                            ->createOptionUsing(fn (array $data): int => Customer::create($data)->getKey())
                            ->createOptionModalHeading('Nuevo cliente')
                            ->createOptionAction(fn (Action $action): Action => $action->modalWidth('3xl')),
                        Select::make('seller_id')->label('Vendedor')
                            ->options(fn (): array => User::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn () => auth()->id())
                            ->required()->searchable()
                            ->disabled(fn (): bool => (bool) auth()->user()?->isSeller())
                            ->dehydrated(),
                        Select::make('financing_plan_id')->label('Plan de financiación')
                            ->options(fn (): array => FinancingPlan::query()->active()->get()
                                ->mapWithKeys(fn (FinancingPlan $plan): array => [
                                    $plan->id => "{$plan->name} (recargo ".rtrim(rtrim(number_format((float) $plan->surcharge_percent, 2, ',', '.'), '0'), ',').'%)',
                                ])->all())
                            ->required()->live(),
                        DatePicker::make('date')->label('Fecha de la venta')->default(now())->required(),
                        DatePicker::make('first_due_date')->label('Vencimiento de la primera cuota')
                            ->default(now()->addMonth())->required()->live(),
                    ]),
                Section::make('Productos')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')->label('Ítems')
                            ->addActionLabel('Agregar producto')
                            ->minItems(1)->required()->live()->columns(4)->defaultItems(1)
                            ->schema([
                                Select::make('product_id')->label('Producto')
                                    ->options(fn (): array => Product::query()->where('active', true)->where('stock', '>', 0)->orderBy('name')
                                        ->get()->mapWithKeys(fn (Product $product): array => [$product->id => "{$product->name} (stock {$product->stock})"])->all())
                                    ->searchable()->required()->live()->columnSpan(2)
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if ($state !== null && ($product = Product::find($state))) {
                                            $set('unit_price', $product->sale_price);
                                        }
                                    }),
                                TextInput::make('quantity')->label('Cantidad')->required()->integer()->minValue(1)->default(1)->live(onBlur: true),
                                TextInput::make('unit_price')->label('Precio unitario')->required()->numeric()->minValue(0)->prefix('$')->live(onBlur: true),
                            ]),
                    ]),
                Section::make('Pago')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
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
}
