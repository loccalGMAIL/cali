<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem vendido. Precio unitario = costo + flete + margen. `pending_quantity` es lo que todavía
 * falta incluir en una orden de compra al proveedor.
 *
 * @property string $unit_cost
 * @property string $unit_freight
 * @property string $unit_margin
 * @property string $unit_price
 * @property string $subtotal
 */
#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_cost', 'unit_freight', 'unit_margin', 'unit_price', 'subtotal', 'pending_quantity'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'unit_freight' => 'decimal:2',
            'unit_margin' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'pending_quantity' => 'integer',
        ];
    }

    /**
     * Ítems con unidades pendientes de compra.
     *
     * @param  Builder<SaleItem>  $query
     * @return Builder<SaleItem>
     */
    #[Scope]
    protected function pendingPurchase(Builder $query): Builder
    {
        return $query->where('pending_quantity', '>', 0);
    }

    /**
     * Descripción para elegirlo al armar una orden: "Heladera — Venta #12, Juan Pérez".
     */
    public function label(): string
    {
        $this->loadMissing(['product', 'sale.customer']);

        return "{$this->product->name} — Venta {$this->sale->number()}, {$this->sale->customer->name}";
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
