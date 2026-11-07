<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceMode: string implements HasLabel
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Auto => 'Automático',
            self::Manual => 'Manual',
        };
    }
}
