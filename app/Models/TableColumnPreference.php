<?php

namespace App\Models;

use Database\Factories\TableColumnPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Columnas que cada usuario eligió mostrar u ocultar en una tabla del panel.
 *
 * @property array<int, array<string, mixed>> $columns
 */
#[Fillable(['user_id', 'table', 'columns'])]
class TableColumnPreference extends Model
{
    /** @use HasFactory<TableColumnPreferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
