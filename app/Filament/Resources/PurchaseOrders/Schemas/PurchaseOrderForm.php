<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\PendingPurchaseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden de compra')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('supplier_id')->label('Proveedor')
                            ->options(fn (): array => Supplier::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()->live()
                            ->createOptionForm(fn (Schema $schema): Schema => SupplierForm::configure($schema))
                            ->createOptionUsing(fn (array $data): int => Supplier::create($data)->getKey())
                            ->createOptionModalHeading('Nuevo proveedor')
                            ->createOptionAction(fn (Action $action): Action => $action->modalWidth('3xl')),
                        DatePicker::make('date')->label('Fecha')->default(now())->required(),
                    ]),
                Section::make('Productos a comprar')
                    ->description('Lo vendido que todavía no se pidió. Lo que no incluyas queda pendiente para otra orden.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')->label('Ítems')
                            ->addActionLabel('Agregar ítem vendido')
                            ->minItems(1)->required()->live()->columns(4)->defaultItems(0)
                            ->hintAction(
                                Action::make('addSupplierPending')
                                    ->label('Agregar pendientes de este proveedor')
                                    ->icon(Heroicon::OutlinedPlus)
                                    ->action(fn (Get $get, Set $set, Component $livewire) => self::addSupplierPending($get, $set, $livewire)),
                            )
                            ->schema([
                                Select::make('sale_item_id')->label('Ítem vendido')
                                    ->options(fn (Component $livewire): array => PendingPurchaseOptions::options(self::order($livewire)))
                                    ->searchable()->required()->live()->columnSpan(3)
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function (?string $state, Set $set, Component $livewire): void {
                                        $set('quantity', PendingPurchaseOptions::availableQuantity($state, self::order($livewire)));
                                    }),
                                TextInput::make('quantity')->label('Cantidad')->required()->integer()->minValue(1)
                                    ->maxValue(fn (Get $get, Component $livewire): int => max(1, PendingPurchaseOptions::availableQuantity($get('sale_item_id'), self::order($livewire)))),
                            ]),
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
     * Suma a la orden los pendientes cuyo proveedor habitual es el elegido (sin repetir los que ya están).
     */
    private static function addSupplierPending(Get $get, Set $set, Component $livewire): void
    {
        if (blank($get('supplier_id'))) {
            Notification::make()->warning()->title('Elegí primero el proveedor')->send();

            return;
        }

        $items = $get('items') ?? [];
        $present = collect($items)->pluck('sale_item_id')->filter()->map(fn ($id): int => (int) $id)->all();

        $added = collect(PendingPurchaseOptions::forSupplier($get('supplier_id'), self::order($livewire)))
            ->reject(fn (array $item): bool => in_array($item['sale_item_id'], $present, true));

        if ($added->isEmpty()) {
            Notification::make()->info()->title('No hay más pendientes de este proveedor')
                ->body('Solo se agregan los productos que lo tienen como proveedor habitual. El resto se puede agregar a mano.')
                ->send();

            return;
        }

        foreach ($added as $item) {
            $items[(string) str()->uuid()] = $item;
        }

        $set('items', $items);
    }

    private static function order(Component $livewire): ?PurchaseOrder
    {
        if (! $livewire instanceof EditRecord) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof PurchaseOrder ? $record : null;
    }
}
