<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\ActivityLoggable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'cuit', 'phone', 'email', 'address', 'notes', 'active'])]
class Supplier extends Model implements ActivityLoggable
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function activityNoun(): string
    {
        return 'el proveedor';
    }

    public function activityTitle(): string
    {
        return (string) $this->name;
    }
}
