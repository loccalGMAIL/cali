<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Registra un movimiento de stock y actualiza el saldo cacheado del producto.
     *
     * Toda variación de stock debe pasar por acá. Corre dentro de una transacción
     * y bloquea la fila del producto para evitar condiciones de carrera.
     *
     * @param  int  $quantity  Con signo: positivo suma, negativo resta.
     */
    public function record(
        Product $product,
        StockMovementType $type,
        int $quantity,
        ?Model $reference = null,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $reference, $user, $notes): StockMovement {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $movement = $locked->stockMovements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $user?->getKey(),
                'notes' => $notes,
            ]);

            $locked->stock += $quantity;
            $locked->save();

            $product->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }
}
