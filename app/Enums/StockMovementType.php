<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Sale => 'Venta',
            self::Adjustment => 'Ajuste',
        };
    }
}
