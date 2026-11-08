<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Deja constancia de los ingresos, las salidas y los intentos de ingreso fallidos.
 * Nunca se guarda la contraseña: solo el email con el que se intentó.
 */
class LogAuthenticationActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function login(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log(ActivityEvent::Login, "Ingresó al sistema: {$event->user->name}", $event->user, user: $event->user);
        }
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log(ActivityEvent::Logout, "Salió del sistema: {$event->user->name}", $event->user, user: $event->user);
        }
    }

    public function failed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');

        $this->logger->log(
            ActivityEvent::LoginFailed,
            'Intento de ingreso fallido con «'.($email !== '' ? $email : 'sin email').'»',
            properties: ['email' => $email],
        );
    }
}
