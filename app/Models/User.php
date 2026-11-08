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
 */
#[Fillable(['name', 'email', 'password', 'role', 'google_id', 'active'])]
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
        ];
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
