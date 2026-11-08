<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\Contracts\ActivityLoggable;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Escribe el registro de actividad: quién hizo qué, cuándo y desde dónde.
 *
 * Los registros de las operaciones de negocio (ventas, compras, cobros y sus anulaciones) se
 * escriben dentro de la misma transacción que la operación: si esta se revierte, el registro
 * también, así el historial nunca cuenta algo que no ocurrió.
 */
class ActivityLogger
{
    /** Atributos que nunca se guardan con su valor. */
    private const MASKED = ['password', 'remember_token'];

    /** Atributos que no aportan al detalle de un cambio. */
    private const SKIPPED = ['id', 'created_at', 'updated_at'];

    private static bool $enabled = true;

    /**
     * Ejecuta algo sin registrar actividad (seeders y datos de demostración).
     */
    public static function withoutLogging(Closure $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
    ): ?ActivityLog {
        if (! self::$enabled) {
            return null;
        }

        $request = app()->runningInConsole() ? null : request();

        return ActivityLog::create([
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'event' => $event,
            'description' => Str::limit($description, 250, '…'),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 250, '') : null,
        ]);
    }

    /**
     * Registra el alta, la modificación o la baja de un maestro (cliente, producto, etc.).
     * Una modificación sin cambios relevantes no deja registro.
     */
    public function logModel(Model&ActivityLoggable $model, ActivityEvent $event): void
    {
        if (! self::$enabled) {
            return;
        }

        $ignored = [...self::SKIPPED, ...$model->activityIgnoredAttributes()];

        if ($event === ActivityEvent::Updated) {
            $changes = [];

            foreach (array_diff_key($model->getChanges(), array_flip($ignored)) as $attribute => $new) {
                $changes[$attribute] = [
                    'old' => $this->value($attribute, $model->getOriginal($attribute)),
                    'new' => $this->value($attribute, $new),
                ];
            }

            if ($changes === []) {
                return;
            }

            $properties = ['changes' => $changes];
        } else {
            $attributes = [];

            foreach (array_diff_key($model->getAttributes(), array_flip($ignored)) as $attribute => $value) {
                $attributes[$attribute] = $this->value($attribute, $value);
            }

            $properties = ['attributes' => $attributes];
        }

        $verb = match ($event) {
            ActivityEvent::Created => 'Creó',
            ActivityEvent::Updated => 'Modificó',
            default => 'Eliminó',
        };

        $this->log($event, "{$verb} {$model->activityNoun()} «{$model->activityTitle()}»", $model, $properties);
    }

    /**
     * Las contraseñas y tokens nunca se guardan: solo queda constancia de que cambiaron.
     */
    private function value(string $attribute, mixed $value): mixed
    {
        if (in_array($attribute, self::MASKED, true)) {
            return '[oculto]';
        }

        return is_scalar($value) || $value === null ? $value : (string) json_encode($value);
    }
}
