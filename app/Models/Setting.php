<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una opción de configuración del sistema, guardada como clave y valor. Se lee y se escribe a
 * través de los servicios que le dan tipo (por ejemplo, `BusinessSettings`).
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model {}
