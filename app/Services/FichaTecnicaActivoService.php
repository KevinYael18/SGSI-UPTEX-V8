<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activo;
use App\Models\Computadora;
use App\Models\Software;
use App\Models\Usuario;
use App\Support\Bitacora;
use App\Support\CatalogoTiposActivo as Catalogo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ficha técnica dinámica de un activo (tipo específico reconocido, campos
 * técnicos y atributos personalizados).
 *
 * Se ejecuta DESPUÉS de ActivoService (que no se modifica): no toca CIA,
 * criticidad, departamento ni relaciones. Guarda:
 *  - campos con destino «ficha» (cómputo/servidores) en la ficha de la tabla
 *    computadoras —la misma del Inventario TI, sin duplicar estructura—;
 *  - el resto en activos.caracteristicas (JSON: campos + personalizados);
 *  - para activos de software, el vínculo con el catálogo central de software.
 */
class FichaTecnicaActivoService
{
    /** Claves de la ficha de cómputo con nombre distinto en la tabla computadoras. */
    private const MAPA_FICHA = ['hostname' => 'nombre_equipo'];

    private const MAX_PERSONALIZADOS = 30;

    /**
     * Columnas de la ficha de cómputo que no admiten NULL: un campo vacío del
     * formulario vuelve a su valor por defecto («desconocido», «operativo»).
     */
    private const DEFAULTS_FICHA = [
        'estado_operativo' => 'operativo', 'so_activacion' => 'desconocido', 'so_soporte' => 'desconocido',
        'antivirus_instalacion' => 'desconocido', 'antivirus_proteccion' => 'desconocido',
    ];

    public function __construct(private readonly InventarioService $inventario)
    {
    }

    /**
     * @param array{subtipo?: ?string, tecnico?: ?array, personalizados?: ?array, ubicacion_detalle?: ?string} $entrada
     */
    public function guardar(Activo $activo, array $entrada, Usuario $actor): void
    {
        $activo->loadMissing('computadora');
        $caracteristicas = $activo->caracteristicas ?? [];
        $antesCampos = $caracteristicas['campos'] ?? [];
        $antesSubtipo = $activo->subtipo;

        // ---- Tipo específico (debe pertenecer a la clasificación general del activo)
        $subtipo = array_key_exists('subtipo', $entrada) ? ($entrada['subtipo'] ?: null) : $activo->subtipo;
        if ($subtipo !== null && Catalogo::categoriaDe($subtipo) !== $activo->tipo->value) {
            if (array_key_exists('subtipo', $entrada)) {
                throw ValidationException::withMessages(['subtipo' => 'El tipo de activo indicado no corresponde a la clasificación general seleccionada.']);
            }
            $subtipo = null; // cambió la clasificación general: el tipo anterior ya no aplica
        }

        // ---- Campos técnicos
        $definidos = $subtipo ? Catalogo::campos($subtipo) : [];
        $tecnico = $this->validar($definidos, (array) ($entrada['tecnico'] ?? []), $activo);
        $paraFicha = [];
        $paraJson = [];
        foreach ($tecnico as $clave => $valor) {
            if (($definidos[$clave]['destino'] ?? null) === 'ficha') {
                $columna = self::MAPA_FICHA[$clave] ?? $clave;
                if ($valor === null && $columna === 'nombre_equipo') {
                    continue; // el nombre del equipo nunca queda vacío (se conserva o se usa el del activo)
                }
                $paraFicha[$columna] = $valor ?? (self::DEFAULTS_FICHA[$columna] ?? null);
            } else {
                $paraJson[$clave] = $valor;
            }
        }
        // Se conservan los valores ya guardados del mismo tipo que no se enviaron.
        $campos = array_filter(
            array_merge(array_intersect_key($antesCampos, $definidos), $paraJson),
            fn ($v) => $v !== null && $v !== ''
        );

        // ---- Atributos personalizados (para activos sin plantilla o datos extra)
        $personalizados = array_key_exists('personalizados', $entrada)
            ? $this->limpiarPersonalizados((array) ($entrada['personalizados'] ?? []))
            : ($caracteristicas['personalizados'] ?? []);

        $activo->forceFill([
            'subtipo' => $subtipo,
            'caracteristicas' => ($campos === [] && $personalizados === []) ? null : array_filter(['campos' => $campos, 'personalizados' => $personalizados]),
        ] + (array_key_exists('ubicacion_detalle', $entrada) ? ['ubicacion_detalle' => $this->texto($entrada['ubicacion_detalle'], 150)] : []))->save();

        // ---- Ficha de cómputo (computadoras, laptops, servidores)
        $plantilla = $subtipo ? Catalogo::plantillaDe($subtipo) : null;
        if (in_array($plantilla, Catalogo::PLANTILLAS_CON_FICHA, true)) {
            $this->guardarFichaComputo($activo, $paraFicha, $actor);
        }

        // ---- Software: vínculo con el catálogo central (relación software ↔ equipos)
        if ($activo->tipo->value === 'software') {
            $this->sincronizarCatalogo($activo, $subtipo, $campos);
        }

        $diferencias = Bitacora::diferencias($antesCampos, $campos);
        if ($diferencias !== [] || $antesSubtipo !== $subtipo || $paraFicha !== []) {
            Bitacora::registrar('activos', $activo->id, $actor->id, 'update', array_filter([
                'evento' => 'ficha_tecnica_actualizada',
                'subtipo' => $antesSubtipo !== $subtipo ? ['antes' => $antesSubtipo, 'despues' => $subtipo] : null,
                'campos' => $diferencias ?: null,
                'ficha_computo' => $paraFicha !== [] ? array_keys($paraFicha) : null,
            ]));
        }
    }

    /** Valores técnicos para precargar el formulario (ficha de cómputo + JSON). */
    public static function valoresTecnicos(Activo $activo): array
    {
        $valores = $activo->caracteristicas['campos'] ?? [];
        $c = $activo->relationLoaded('computadora') ? $activo->computadora : $activo->computadora()->first();
        if ($c !== null && $activo->subtipo) {
            foreach (Catalogo::campos($activo->subtipo) as $clave => $def) {
                if (($def['destino'] ?? null) === 'ficha') {
                    $valor = $c->getAttribute(self::MAPA_FICHA[$clave] ?? $clave);
                    $valores[$clave] = $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : $valor;
                }
            }
        }

        return array_filter($valores, fn ($v) => $v !== null && $v !== '');
    }

    // ------------------------------------------------------------------ privado

    private function validar(array $definidos, array $tecnico, Activo $activo): array
    {
        // Solo se aceptan claves definidas para el tipo; el resto se ignora.
        $datos = [];
        foreach (array_intersect_key($tecnico, $definidos) as $clave => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
            }
            if ($valor === '' || $valor === []) {
                $valor = null;
            }
            if ($valor !== null && $definidos[$clave]['tipo'] === 'mac') {
                $limpia = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $valor));
                $valor = strlen($limpia) === 12 ? implode(':', str_split($limpia, 2)) : (string) $valor;
            }
            $datos[$clave] = $valor;
        }

        $reglas = [];
        $atributos = [];
        foreach ($datos as $clave => $valor) {
            $def = $definidos[$clave];
            $atributos[$clave] = mb_strtolower($def['etiqueta'], 'UTF-8');
            $reglas[$clave] = match ($def['tipo']) {
                'texto', 'textarea' => ['nullable', 'string', 'max:' . ($def['max'] ?? 255)],
                'entero' => ['nullable', 'integer', 'min:' . ($def['min'] ?? 0), 'max:' . ($def['maxv'] ?? PHP_INT_MAX)],
                'numero' => ['nullable', 'numeric', 'min:' . ($def['min'] ?? 0), 'max:' . ($def['maxv'] ?? PHP_INT_MAX)],
                'fecha' => array_merge(['nullable', 'date'], empty($def['futura']) ? ['before_or_equal:today'] : []),
                'select' => ['nullable', Rule::in(array_column($def['opciones'] ?? [], 'value'))],
                'ip' => ['nullable', 'ip'],
                'mac' => ['nullable', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $validador = Validator::make($datos, $reglas, [
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'date' => 'La fecha de :attribute no es válida.',
            'before_or_equal' => 'La fecha de :attribute no puede ser futura.',
            'in' => 'El valor de :attribute no es válido.',
            'ip' => 'La :attribute no es válida.',
            'regex' => 'La :attribute debe tener 12 dígitos hexadecimales (p. ej. 00:1A:2B:3C:4D:5E).',
        ], $atributos);

        $validador->after(function ($v) use ($datos, $definidos, $activo) {
            // Nunca se guardan claves de producto: solo referencias de contrato.
            if (!empty($datos['referencia_licencia']) && preg_match('/^([A-Z0-9]{4,6}-){3,}[A-Z0-9]{4,6}$/i', $datos['referencia_licencia'])) {
                $v->errors()->add('referencia_licencia', 'Parece una clave de producto. Registra solo una referencia (contrato u orden de compra), nunca la clave.');
            }
            // Duplicados de serie, inventario y MAC en todo el inventario de activos.
            foreach ($datos as $clave => $valor) {
                if ($valor === null || empty($definidos[$clave]['unico'])) {
                    continue;
                }
                $columna = self::MAPA_FICHA[$clave] ?? $clave;
                $enActivos = Activo::query()->whereKeyNot($activo->id)->where("caracteristicas->campos->{$clave}", $valor)->exists();
                $enFichas = in_array($columna, ['numero_serie', 'numero_inventario', 'direccion_mac'], true)
                    && Computadora::withTrashed()->where($columna, $valor)->where('activo_id', '!=', $activo->id)->exists();
                if ($enActivos || $enFichas) {
                    $v->errors()->add($clave, "Ya existe otro activo con ese {$definidos[$clave]['etiqueta']}.");
                }
            }
        });

        if ($validador->fails()) {
            $errores = [];
            foreach ($validador->errors()->messages() as $clave => $mensajes) {
                $errores["tecnico.{$clave}"] = $mensajes;
            }
            throw ValidationException::withMessages($errores);
        }

        foreach ($datos as $clave => $valor) {
            if ($valor !== null && in_array($definidos[$clave]['tipo'], ['entero'], true)) {
                $datos[$clave] = (int) $valor;
            } elseif ($valor !== null && $definidos[$clave]['tipo'] === 'numero') {
                $datos[$clave] = (float) $valor;
            }
        }

        return $datos;
    }

    private function limpiarPersonalizados(array $lista): array
    {
        $limpios = [];
        foreach ($lista as $i => $item) {
            $nombre = $this->texto($item['nombre'] ?? null, 60);
            $valor = $this->texto($item['valor'] ?? null, 255);
            if ($nombre === null && $valor === null) {
                continue;
            }
            if ($nombre === null) {
                throw ValidationException::withMessages(["personalizados.{$i}.nombre" => 'Escribe el nombre de la característica.']);
            }
            $limpios[] = ['nombre' => $nombre, 'valor' => $valor];
        }
        if (count($limpios) > self::MAX_PERSONALIZADOS) {
            throw ValidationException::withMessages(['personalizados' => 'Se permiten como máximo ' . self::MAX_PERSONALIZADOS . ' características personalizadas.']);
        }

        return $limpios;
    }

    private function texto(mixed $valor, int $max): ?string
    {
        if ($valor === null) {
            return null;
        }
        $t = trim((string) $valor);

        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    private function guardarFichaComputo(Activo $activo, array $paraFicha, Usuario $actor): void
    {
        $computadora = $activo->computadora;
        if ($computadora !== null) {
            if ($paraFicha !== []) {
                // Se actualiza directamente la ficha (sin renombrar el activo: el
                // hostname puede ser distinto del nombre descriptivo del activo).
                $computadora->fill($paraFicha)->save();
            }

            return;
        }
        $this->inventario->crearComputadora(
            ['activo_id' => $activo->id, 'nombre_equipo' => $paraFicha['nombre_equipo'] ?? $activo->nombre] + $paraFicha,
            $actor
        );
        $activo->load('computadora');
    }

    private function sincronizarCatalogo(Activo $activo, ?string $subtipo, array $campos): void
    {
        $categoria = match ($subtipo) {
            'sistema_operativo' => 'sistema_operativo', 'antivirus', 'seguridad' => 'seguridad', 'productividad' => 'ofimatica',
            'desarrollo' => 'desarrollo', 'base_datos' => 'base_datos', 'academico' => 'academico', 'administrativo' => 'administrativo',
            'diseno' => 'diseno', 'virtualizacion' => 'virtualizacion', 'laboratorio' => 'laboratorio', default => 'otro',
        };
        $licencia = ['propietaria' => 'comercial'][$campos['tipo_licencia'] ?? ''] ?? ($campos['tipo_licencia'] ?? null);
        $fabricante = $campos['fabricante'] ?? null;

        $programa = Software::query()->where('activo_id', $activo->id)->first()
            ?? Software::query()->whereNull('activo_id')
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($activo->nombre, 'UTF-8')])
                ->where(fn ($q) => $fabricante ? $q->where('fabricante', $fabricante)->orWhereNull('fabricante') : $q)
                ->first();

        $datos = array_filter([
            'nombre' => $activo->nombre,
            'fabricante' => $fabricante ?? $programa?->fabricante,
            'categoria' => $categoria === 'otro' && $programa ? $programa->categoria : $categoria,
            'tipo_licencia' => $licencia ?? $programa?->tipo_licencia,
        ], fn ($v) => $v !== null);
        if ($subtipo === 'antivirus') {
            $datos['es_antivirus'] = true;
        }

        // No se fuerza el vínculo si ya existe OTRA entrada con el mismo nombre y fabricante.
        $choque = Software::query()->where('nombre', $datos['nombre'])->where('fabricante', $datos['fabricante'] ?? null)
            ->when($programa, fn ($q) => $q->whereKeyNot($programa->id))->exists();
        if ($choque) {
            return;
        }

        if ($programa === null) {
            (new Software())->forceFill($datos + ['activo_id' => $activo->id, 'estado_autorizacion' => 'pendiente_verificacion'])->save();
        } else {
            $programa->forceFill($datos + ['activo_id' => $activo->id])->save();
        }
    }
}
