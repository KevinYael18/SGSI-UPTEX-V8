<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;

/**
 * Punto único para escribir en la bitácora técnica (activity_log) desde
 * servicios que no disparan un evento de dominio propio (seguridad de
 * cuentas, inventario de equipos, etc.). Mismo formato que el listener
 * RegistrarActivityLogGenerico: `accion` es create|update|delete|restore y
 * el detalle del suceso va en `cambios.evento`.
 *
 * Nunca guarda secretos: cualquier clave que parezca contraseña, token o
 * hash se elimina antes de escribir.
 */
final class Bitacora
{
    private const CLAVES_SENSIBLES = ['password', 'password_hash', 'password_confirmation', 'token', 'remember_token', 'mail_password', 'secret'];

    public static function registrar(string $entidad, int $entidadId, ?int $usuarioId, string $accion, array $cambios): ActivityLog
    {
        // Cambios de inventario o de cuentas alteran indicadores del dashboard
        // (equipos, antivirus, usuarios suspendidos): se invalida su caché.
        if (in_array($entidad, ['computadoras', 'laboratorios', 'software_catalogo', 'usuarios'], true)
            && ($cambios['evento'] ?? '') !== 'login_fallido') {
            CacheVersionado::invalidar('dashboard-kpis');
        }

        return ActivityLog::query()->create([
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'cambios' => self::limpiar($cambios),
            'created_at' => now(),
        ]);
    }

    /**
     * Diferencias legibles "antes → después" solo de los campos que cambiaron.
     *
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public static function diferencias(array $antes, array $despues): array
    {
        $resultado = [];
        foreach ($despues as $campo => $valorNuevo) {
            $valorAnterior = $antes[$campo] ?? null;
            if ($valorAnterior instanceof \BackedEnum) {
                $valorAnterior = $valorAnterior->value;
            }
            if ($valorNuevo instanceof \BackedEnum) {
                $valorNuevo = $valorNuevo->value;
            }
            if ((string) json_encode($valorAnterior) !== (string) json_encode($valorNuevo)) {
                $resultado[$campo] = ['antes' => $valorAnterior, 'despues' => $valorNuevo];
            }
        }

        return self::limpiar($resultado);
    }

    private static function limpiar(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && in_array(strtolower($clave), self::CLAVES_SENSIBLES, true)) {
                $datos[$clave] = '[omitido]';
            } elseif (is_array($valor)) {
                $datos[$clave] = self::limpiar($valor);
            }
        }

        return $datos;
    }
}
