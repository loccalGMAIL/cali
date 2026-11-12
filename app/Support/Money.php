<?php

namespace App\Support;

class Money
{
    /**
     * Pesos sin centavos y con espacio fijo, para tarjetas del dashboard: `$ 1.234.567`.
     * El espacio no se parte, así el signo no queda solo en una línea.
     */
    public static function whole(float|int|string|null $amount): string
    {
        return '$'."\u{00A0}".number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Monto sin signo ni centavos, para las celdas de una grilla: `1.234.567`. El cero queda
     * como un guion, así se distingue de un vistazo dónde hay deuda.
     */
    public static function cell(float|int|string|null $amount): string
    {
        return (float) $amount == 0.0 ? '–' : number_format((float) $amount, 0, ',', '.');
    }

    /** Formatea un monto en pesos argentinos: `$ 1.234,56`. */
    public static function format(float|int|string|null $amount): string
    {
        return '$ '.number_format((float) $amount, 2, ',', '.');
    }
}
