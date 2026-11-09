<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Borrador → Emitida (se lleva al proveedor) → Recibida (se cargó la factura). Mientras no
 * esté recibida se puede editar o anular; al anularla, lo pedido vuelve a "pendiente de compra".
 */
enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitida',
            self::Received => 'Recibida',
            self::Cancelled => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Una orden abierta todavía se puede editar, recibir o anular.
     */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Issued;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Draft->value, self::Issued->value];
    }
}
