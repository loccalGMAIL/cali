<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\HtmlString;

/**
 * Arma el detalle de un registro de actividad: qué cambió (antes y después), con qué valores
 * se creó o se eliminó algo, o los datos de una operación (motivo, monto…).
 */
class ActivityDetails
{
    public static function render(ActivityLog $log): HtmlString
    {
        $properties = $log->properties ?? [];

        if ($properties === []) {
            return new HtmlString('<span class="text-sm text-gray-500">Sin detalle adicional.</span>');
        }

        if (isset($properties['changes']) && is_array($properties['changes'])) {
            $rows = '';

            foreach ($properties['changes'] as $attribute => $change) {
                $rows .= '<tr>'
                    .'<td style="padding:3px 16px 3px 0; font-weight:600">'.e(ActivityLabels::attribute((string) $attribute)).'</td>'
                    .'<td style="padding:3px 16px">'.self::value($change['old'] ?? null).'</td>'
                    .'<td style="padding:3px 0 3px 16px">'.self::value($change['new'] ?? null).'</td>'
                    .'</tr>';
            }

            return new HtmlString(
                '<table class="text-sm"><thead><tr style="text-align:left">'
                .'<th style="padding:3px 16px 3px 0">Campo</th><th style="padding:3px 16px">Antes</th><th style="padding:3px 0 3px 16px">Después</th>'
                .'</tr></thead><tbody>'.$rows.'</tbody></table>',
            );
        }

        $values = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : $properties;

        $rows = '';

        foreach ($values as $attribute => $value) {
            $rows .= '<tr>'
                .'<td style="padding:3px 16px 3px 0; font-weight:600">'.e(ActivityLabels::attribute((string) $attribute)).'</td>'
                .'<td style="padding:3px 0">'.self::value($value).'</td>'
                .'</tr>';
        }

        return new HtmlString('<table class="text-sm"><tbody>'.$rows.'</tbody></table>');
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '<span style="color:#9ca3af">—</span>',
            is_bool($value) => $value ? 'Sí' : 'No',
            is_array($value) => e((string) json_encode($value, JSON_UNESCAPED_UNICODE)),
            default => e((string) $value),
        };
    }
}
