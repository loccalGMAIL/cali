<?php

namespace App\Services\Reports;

/**
 * Un informe: calcula sus datos a partir de unos filtros. La página del panel y la versión
 * imprimible usan el mismo informe, así que muestran exactamente los mismos números.
 *
 * Los montos viajan como strings decimales con 2 decimales y todo excluye lo anulado.
 */
interface Report
{
    /**
     * Identificador en la URL de la versión imprimible y nombre del parcial que lo dibuja.
     */
    public static function slug(): string;

    public function title(): string;

    /**
     * Completa y corrige los filtros recibidos (del formulario o del query string): lo que
     * falta o no es válido toma su valor por defecto.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array;

    /**
     * Filtros aplicados, en palabras, para el encabezado de la versión imprimible.
     *
     * @param  array<string, mixed>  $filters  Ya normalizados.
     * @return array<string, string>
     */
    public function describe(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters  Ya normalizados.
     * @return array<string, mixed>
     */
    public function build(array $filters): array;
}
