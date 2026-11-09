<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            // Precio unitario = costo + margen, ambos cargados al momento de la venta.
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('unit_margin', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            // Unidades que todavía no se incluyeron en una orden de compra. No hay stock:
            // todo lo vendido se compra después al proveedor.
            $table->unsignedInteger('pending_quantity')->default(0);
            $table->timestamps();

            $table->index('pending_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
