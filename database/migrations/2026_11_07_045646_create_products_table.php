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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->decimal('last_cost', 12, 2)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->string('price_mode')->default('auto');
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
