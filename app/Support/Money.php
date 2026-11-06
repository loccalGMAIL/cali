<?php

namespace App\Support;

class Money
{
    /** Formatea un monto en pesos argentinos: `$ 1.234,56`. */
    public static function format(float|int|string|null $amount): string
    {
        return '$ '.number_format((float) $amount, 2, ',', '.');
    }
}
