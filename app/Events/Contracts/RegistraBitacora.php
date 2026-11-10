<?php

declare(strict_types=1);

namespace App\Events\Contracts;

/**
 * Refactor aplicado en Fase 3.2 (detectado en auditoría interna antes de
 * seguir creciendo): en vez de un Listener distinto por cada par
 * Entidad+Accion (RegistrarActivityLogActivo, RegistrarActivityLogRiesgo...
 * hubiera sido duplicación N veces), todo evento que deba dejar traza en
 * `activity_log` implementa este contrato. Un único listener genérico
 * (RegistrarActivityLogGenerico) sirve a todos los módulos (DIP + OCP:
 * agregar bitácora a un módulo nuevo no toca el listener).
 */
interface RegistraBitacora
{
    public function entidad(): string;

    public function entidadId(): int;

    public function usuarioId(): ?int;

    public function accion(): string; // create|update|delete|restore

    public function cambios(): array;
}