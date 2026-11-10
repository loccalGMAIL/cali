<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\ActivityLoggable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole $role
 * @property bool $is_default_seller
 */
#[Fillable(['name', 'email', 'password', 'role', 'google_id', 'active', 'is_default_seller'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements ActivityLoggable, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'is_default_seller' => 'boolean',
        ];
    }

    /**
     * Solo puede haber un vendedor por defecto: al marcar uno se desmarca el anterior.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if ($user->is_default_seller && ($user->wasRecentlyCreated || $user->wasChanged('is_default_seller'))) {
                static::query()->whereKeyNot($user->getKey())->where('is_default_seller', true)
                    ->get()
                    ->each(fn (User $other): bool => $other->update(['is_default_seller' => false]));
            }
        });
    }

    /**
     * Vendedor que se propone al cargar una venta (si está activo).
     */
    public static function defaultSeller(): ?self
    {
        return static::query()->where('is_default_seller', true)->where('active', true)->first();
    }

    /**
     * Un usuario con ventas, cobros, órdenes o facturas de compra a su nombre no se puede borrar:
     * se perdería el historial. En ese caso hay que desactivarlo.
     */
    public function hasRegisteredOperations(): bool
    {
        return Sale::query()->where('seller_id', $this->getKey())->exists()
            || Payment::query()->where('collected_by', $this->getKey())->exists()
            || PurchaseOrder::query()->where('user_id', $this->getKey())->exists()
            || Purchase::query()->where('user_id', $this->getKey())->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSellers(Builder $query): void
    {
        $query->where('role', UserRole::Seller->value)->where('active', true);
    }

    public function activityNoun(): string
    {
        return 'el usuario';
    }

    public function activityTitle(): string
    {
        return (string) $this->name;
    }

    /**
     * @return list<string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['remember_token'];
    }
}
