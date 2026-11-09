<?php

namespace App\Models\Contracts;

/**
 * Un modelo cuyas altas, modificaciones y bajas se registran en el historial de actividad.
 * El trait LogsActivity engancha los eventos; cada modelo dice cómo nombrarse.
 */
interface ActivityLoggable
{
    /**
     * Cómo se nombra el registro en la descripción, con artículo: "el cliente".
     */
    public function activityNoun(): string;

    /**
     * Cómo se identifica el registro: normalmente su nombre.
     */
    public function activityTitle(): string;

    /**
     * Atributos que cambian solos (por ejemplo el último costo) y no deben ensuciar el historial.
     *
     * @return list<string>
     */
    public function activityIgnoredAttributes(): array;
}
