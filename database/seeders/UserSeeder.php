<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios administradores del equipo. Es idempotente: si el usuario ya existe (por email) se
 * actualizan sus datos, así que se puede volver a ejecutar sin duplicar nada.
 */
class UserSeeder extends Seeder
{
    /**
     * @var list<array{name: string, email: string, password: string}>
     */
    private const ADMINS = [
        ['name' => 'Claudio', 'email' => 'loccal@gmail.com', 'password' => 'Claudio123'],
        ['name' => 'Carlos Casaretto', 'email' => 'carloscasaretto@hotmail.com', 'password' => 'Carlos#123'],
    ];

    public function run(): void
    {
        foreach (self::ADMINS as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => $admin['password'],
                    'role' => UserRole::Admin,
                    'active' => true,
                ],
            );
        }
    }
}
