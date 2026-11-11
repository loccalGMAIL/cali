<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Categoría opcional: sirve para ordenar y filtrar el catálogo.
            $table->foreignId('category_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        // Los productos cargados sin código reciben el automático (PRD-000123).
        DB::table('products')->whereNull('sku')->orderBy('id')->each(function (object $product): void {
            $sku = 'PRD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
            $candidate = $sku;
            $suffix = 2;

            while (DB::table('products')->where('sku', $candidate)->exists()) {
                $candidate = $sku.'-'.$suffix++;
            }

            DB::table('products')->where('id', $product->id)->update(['sku' => $candidate]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
