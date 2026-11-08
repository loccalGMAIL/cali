<?php

namespace App\Models\Concerns;

use App\Enums\ActivityEvent;
use App\Models\Contracts\ActivityLoggable;
use App\Services\ActivityLogger;

/**
 * Registra en el historial el alta, la modificación y la baja de un maestro.
 * El modelo debe implementar ActivityLoggable.
 *
 * @phpstan-require-implements ActivityLoggable
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (self $model) => app(ActivityLogger::class)->logModel($model, ActivityEvent::Created));
        static::updated(fn (self $model) => app(ActivityLogger::class)->logModel($model, ActivityEvent::Updated));
        static::deleted(fn (self $model) => app(ActivityLogger::class)->logModel($model, ActivityEvent::Deleted));
    }

    /**
     * @return list<string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [];
    }
}
