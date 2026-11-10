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
        Schema::table('products', function (Blueprint $table) {
            // Flete por unidad (opcional): se sugiere al vender y forma parte del precio.
            $table->decimal('freight', 12, 2)->default(0)->after('last_cost');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Precio unitario = costo + flete + margen, los tres cargados al momento de la venta.
            $table->decimal('unit_freight', 12, 2)->default(0)->after('unit_cost');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Flete de los ítems: integra el total y se divide en las cuotas junto con el resto.
            $table->decimal('total_freight', 12, 2)->default(0)->after('total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('total_freight');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unit_freight');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('freight');
        });
    }
};
