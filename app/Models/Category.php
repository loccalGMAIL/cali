<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\ActivityLoggable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupa los productos del catálogo. Es opcional: un producto puede no tener categoría.
 */
#[Fillable(['name', 'active'])]
class Category extends Model implements ActivityLoggable
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function activityNoun(): string
    {
        return 'la categoría';
    }

    public function activityTitle(): string
    {
        return (string) $this->name;
    }
}
