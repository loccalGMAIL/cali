<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'sale_item_id' => SaleItem::factory(),
            'product_id' => fn (array $attributes): int => SaleItem::query()->findOrFail($attributes['sale_item_id'])->product_id,
            'quantity' => 1,
            'received_quantity' => null,
        ];
    }
}
