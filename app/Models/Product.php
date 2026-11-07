<?php

namespace App\Models;

use App\Enums\PriceMode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PriceMode $price_mode
 * @property string $sale_price
 */
#[Fillable([
    'sku', 'name', 'brand', 'description', 'active', 'stock', 'min_stock',
    'last_cost', 'margin_percent', 'price_mode', 'sale_price',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'last_cost' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'price_mode' => PriceMode::class,
            'sale_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // En modo automático el precio siempre se deriva del costo y el margen.
        static::saving(function (Product $product): void {
            if ($product->price_mode === PriceMode::Auto) {
                $product->sale_price = $product->calculateAutoPrice();
            }
        });
    }

    /**
     * Precio automático: último costo más el margen del producto.
     */
    public function calculateAutoPrice(): string
    {
        $price = (float) $this->last_cost * (1 + (float) $this->margin_percent / 100);

        return number_format(round($price, 2), 2, '.', '');
    }

    public function isLowStock(): bool
    {
        return $this->stock < $this->min_stock;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    #[Scope]
    protected function lowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<', 'min_stock');
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
}
