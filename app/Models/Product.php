<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\ActivityLoggable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No se maneja stock: todo lo vendido se compra después al proveedor con una orden de compra.
 * El último costo lo fija la factura de compra más reciente y se sugiere al cargar una venta.
 * El flete (opcional, por unidad) también se sugiere al vender y se suma al precio.
 *
 * @property string $last_cost
 * @property string $freight
 */
#[Fillable(['sku', 'name', 'brand', 'description', 'active', 'supplier_id', 'last_cost', 'freight'])]
class Product extends Model implements ActivityLoggable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    public const AUTOMATIC_SKU_PREFIX = 'PROD-';

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (blank($product->sku)) {
                $product->sku = static::nextAutomaticSku();
            }
        });
    }

    /**
     * Siguiente código correlativo del sistema (PROD-00001, PROD-00002, ...).
     */
    public static function nextAutomaticSku(): string
    {
        $lastNumber = static::query()
            ->where('sku', 'like', self::AUTOMATIC_SKU_PREFIX.'%')
            ->pluck('sku')
            ->map(fn (string $sku): int => preg_match('/^'.preg_quote(self::AUTOMATIC_SKU_PREFIX, '/').'(\d+)$/', $sku, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return self::AUTOMATIC_SKU_PREFIX.str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_cost' => 'decimal:2',
            'freight' => 'decimal:2',
        ];
    }

    /**
     * Proveedor habitual del producto.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function activityNoun(): string
    {
        return 'el producto';
    }

    public function activityTitle(): string
    {
        return (string) $this->name;
    }

    /**
     * El último costo cambia solo con cada factura de compra: no ensucia el historial.
     *
     * @return list<string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['last_cost'];
    }
}
