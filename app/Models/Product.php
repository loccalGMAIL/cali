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
 * Si no se carga un código, se genera uno automático a partir del id (PRD-000123).
 *
 * @property string $last_cost
 * @property string $freight
 */
#[Fillable(['sku', 'name', 'brand', 'description', 'active', 'supplier_id', 'category_id', 'last_cost', 'freight'])]
class Product extends Model implements ActivityLoggable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    public const SKU_PREFIX = 'PRD-';

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->sku)) {
                $product->sku = null;
            }
        });

        static::saved(function (Product $product): void {
            if ($product->sku === null) {
                $product->sku = static::generateSku($product->getKey());
                $product->saveQuietly();
            }
        });
    }

    /**
     * Código automático a partir del id; si alguien ya lo cargó a mano, se le agrega un sufijo.
     */
    public static function generateSku(int $id): string
    {
        $sku = self::SKU_PREFIX.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $candidate = $sku;
        $suffix = 2;

        while (static::query()->where('sku', $candidate)->exists()) {
            $candidate = $sku.'-'.$suffix++;
        }

        return $candidate;
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
     * Categoría opcional del producto.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
