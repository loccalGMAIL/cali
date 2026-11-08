<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Partial => 'Parcial',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Estados de una cuota que todavía se debe (excluye las pagadas y las anuladas).
     *
     * @return list<string>
     */
    public static function unpaidValues(): array
    {
        return [self::Pending->value, self::Partial->value];
    }
}
