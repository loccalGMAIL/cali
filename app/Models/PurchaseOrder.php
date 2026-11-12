<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Pedido a un proveedor armado con lo vendido que todavía no se compró.
 *
 * @property PurchaseOrderStatus $status
 */
#[Fillable([
    'supplier_id', 'date', 'status', 'notes', 'user_id', 'issued_at',
    'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => PurchaseOrderStatus::class,
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Número con el que se identifica la orden de compra: `OC-00012`.
     */
    public static function numberFor(int|string|null $id): string
    {
        return $id === null ? '—' : 'OC-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function number(): string
    {
        return static::numberFor($this->getKey());
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderStatus::Cancelled;
    }

    /**
     * Órdenes que todavía no se recibieron ni se anularon.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', PurchaseOrderStatus::openValues());
    }

    /**
     * Lo pedido agrupado por producto (así se imprime y así se carga la factura).
     * Requiere la relación items.product cargada o la carga.
     *
     * @return Collection<int, array{product: Product, quantity: int, received_quantity: int|null, reference_cost: string}>
     */
    public function linesByProduct(): Collection
    {
        $this->loadMissing(['items.product', 'items.saleItem']);

        return $this->items
            ->groupBy('product_id')
            ->map(function (Collection $items): array {
                $quantity = (int) $items->sum('quantity');
                $received = $items->every(fn (PurchaseOrderItem $item): bool => $item->received_quantity === null)
                    ? null
                    : (int) $items->sum('received_quantity');

                // Costo de referencia: el promedio ponderado del costo cargado en las ventas.
                $cost = $items->reduce(
                    fn (string $carry, PurchaseOrderItem $item): string => bcadd($carry, bcmul((string) $item->quantity, (string) $item->saleItem->unit_cost, 2), 2),
                    '0.00',
                );

                return [
                    'product' => $items->first()->product,
                    'quantity' => $quantity,
                    'received_quantity' => $received,
                    'reference_cost' => $quantity > 0 ? bcdiv($cost, (string) $quantity, 2) : '0.00',
                ];
            })
            ->sortBy(fn (array $line): string => $line['product']->name)
            ->values();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * La factura vigente con la que se recibió la orden.
     *
     * @return HasOne<Purchase, $this>
     */
    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class)->whereNull('cancelled_at')->latestOfMany();
    }
}
