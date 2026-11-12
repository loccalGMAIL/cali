<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lectura tolerante de los filtros de un informe: un valor ausente o inválido nunca rompe,
 * toma el valor por defecto.
 */
class ReportFilters
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public static function date(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * Rango de fechas ordenado; por defecto, el mes en curso.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dateRange(array $input): array
    {
        $today = CarbonImmutable::today();
        $from = self::date($input['from'] ?? null, $today->startOfMonth());
        $to = self::date($input['to'] ?? null, $today->endOfMonth()->startOfDay());

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    /**
     * Rango ya normalizado, en palabras: `01/11/2026 al 30/11/2026`.
     */
    public static function period(string $from, string $to): string
    {
        return CarbonImmutable::parse($from)->format('d/m/Y').' al '.CarbonImmutable::parse($to)->format('d/m/Y');
    }

    /**
     * Primer día del mes indicado como `Y-m`; por defecto, el mes en curso.
     */
    public static function month(mixed $value): CarbonImmutable
    {
        $default = CarbonImmutable::today()->startOfMonth();

        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $default;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
    }

    public static function boolean(mixed $value, bool $default): bool
    {
        return $value === null || $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function integer(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Mes abreviado en castellano, sin depender del idioma configurado: `Nov-26`.
     */
    public static function monthLabel(CarbonImmutable $month): string
    {
        return self::MONTHS[$month->month - 1].'-'.$month->format('y');
    }

    /**
     * Mes completo para títulos y selectores: `Nov 2026`.
     */
    public static function monthTitle(CarbonImmutable $month): string
    {
        return self::MONTHS[$month->month - 1].' '.$month->format('Y');
    }
}
