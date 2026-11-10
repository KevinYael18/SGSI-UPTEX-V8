<?php

declare(strict_types=1);

namespace App\Events\Contracts;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Análogo a RegistraBitacora pero para notificaciones: cualquier evento
 * de dominio que deba avisar a uno o varios usuarios implementa esto.
 * Un único listener (NotificarPorEventoDominio) sirve a todos los
 * módulos — agregar notificaciones a un módulo nuevo no toca el listener.
 */
interface GeneraNotificacion
{
    /** @return Collection<int, \App\Models\Usuario> */
    public function destinatarios(): Collection;

    public function notificacion(): Notification;
}