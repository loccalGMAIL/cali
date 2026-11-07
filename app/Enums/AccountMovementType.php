<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountMovementType: string implements HasLabel
{
    case Sale = 'sale';
    case Payment = 'payment';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'Venta',
            self::Payment => 'Cobro',
            self::Adjustment => 'Ajuste',
        };
    }
}
