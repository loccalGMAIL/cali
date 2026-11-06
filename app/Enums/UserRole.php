<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Seller = 'seller';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Seller => 'Vendedor',
        };
    }
}
