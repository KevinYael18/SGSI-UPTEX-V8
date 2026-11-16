<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Computadora;
use App\Models\Laboratorio;
use App\Models\Software;
use App\Models\Usuario;
use App\Support\Bitacora;
use App\Support\InventarioCatalogos as C;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Throwable;

/**
 * Importación masiva de computadoras desde CSV o Excel.
 *
 * Flujo en dos pasos con el MISMO archivo:
 *  1) analizar()  -> vista previa: fila por fila indica si es nueva, duplicada
 *                    (en el sistema o dentro del archivo) o tiene errores.
 *  2) importar()  -> aplica. Los duplicados se OMITEN por defecto; solo se
 *                    actualizan si el usuario lo autoriza expresamente
 *                    (`duplicados = actualizar`). Nunca se crean edificios,
 *                    laboratorios ni programas inexistentes: se reportan.
 */
class ImportacionComputadorasService
{
    public const COLUMNAS = [
        'nombre_equipo', 'numero_inventario', 'numero_serie', 'direccion_mac', 'direccion_ip', 'edificio', 'laboratorio',
        'ubicacion_fisica', 'estado_operativo', 'fabricante', 'modelo', 'procesador', 'ram_gb', 'almacenamiento_gb',
        'tipo_almacenamiento', 'tarjeta_grafica', 'fecha_adquisicion', 'garantia_hasta', 'so_nombre', 'so_edicion',
        'so_version', 'so_arquitectura', 'so_activacion', 'so_ultima_actualizacion', 'so_soporte', 'antivirus_nombre',
        'antivirus_fabricante', 'antivirus_version', 'antivirus_instalacion', 'antivirus_proteccion',
        'antivirus_ultima_actualizacion', 'software', 'observaciones',
    ];

    private const FECHAS = ['fecha_adquisicion', 'garantia_hasta', 'so_ultima_actualizacion', 'antivirus_ultima_actualizacion'];

    private const ENUMS = [
        'estado_operativo' => C::ESTADOS_OPERATIVOS,
        'tipo_almacenamiento' => C::TIPOS_ALMACENAMIENTO,
        'so_arquitectura' => C::ARQUITECTURAS,
        'so_activacion' => C::ACTIVACION_SO,
        'so_soporte' => C::SOPORTE_SO,
        'antivirus_instalacion' => C::INSTALACION_ANTIVIRUS,
        'antivirus_proteccion' => C::PROTECCION_ANTIVIRUS,
    ];

    public function __construct(private readonly InventarioService $inventario)
    {
    }

    /** @return array{filas: array, resumen: array} */
    public function analizar(UploadedFile $archivo, ?int $laboratorioPorDefecto): array
    {
        $filas = $this->leer($archivo);
        $laboratorios = Laboratorio::query()->with('edificio')->get();
        $catalogo = Software::query()->get(['id', 'nombre']);
        $vistos = ['numero_inventario' => [], 'numero_serie' => [], 'direccion_mac' => []];
        $resultado = [];

        foreach ($filas as $indice => $crudo) {
            $numeroFila = $indice + 2; // fila 1 = encabezados
            $errores = [];
            $avisos = [];
            $datos = $this->normalizar($crudo, $errores);

            // Laboratorio: columnas edificio/laboratorio, o el elegido en el diálogo.
            $laboratorio = null;
            if (!empty($crudo['laboratorio'])) {
                $laboratorio = $laboratorios->first(fn ($l) => $this->igual($l->nombre, $crudo['laboratorio'])
                    && (empty($crudo['edificio']) || $this->igual($l->edificio?->nombre, $crudo['edificio'])));
                if ($laboratorio === null) {
                    $errores[] = "El laboratorio «{$crudo['laboratorio']}»" . (!empty($crudo['edificio']) ? " del edificio «{$crudo['edificio']}»" : '') . ' no está registrado.';
                }
            } elseif ($laboratorioPorDefecto) {
                $laboratorio = $laboratorios->firstWhere('id', $laboratorioPorDefecto);
            }
            $datos['laboratorio_id'] = $laboratorio?->id;

            // Software: "Nombre@versión; Nombre 2" -> solo programas del catálogo.
            $instalaciones = [];
            foreach (array_filter(array_map('trim', explode(';', (string) ($crudo['software'] ?? '')))) as $entrada) {
                [$nombre, $version] = array_pad(array_map('trim', explode('@', $entrada, 2)), 2, null);
                $programa = $catalogo->first(fn ($s) => $this->igual($s->nombre, $nombre));
                if ($programa === null) {
                    $avisos[] = "«{$nombre}» no está en el catálogo de software; no se registrará (agrégalo al catálogo primero).";
                } else {
                    $instalaciones[$programa->id] = ['version' => $version ?: null];
                }
            }

            $validador = Validator::make($datos, [
                'nombre_equipo' => ['required', 'string', 'max:100'],
                'numero_inventario' => ['nullable', 'string', 'max:60'],
                'numero_serie' => ['nullable', 'string', 'max:100'],
                'direccion_mac' => ['nullable', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
                'direccion_ip' => ['nullable', 'ip'],
                'ram_gb' => ['nullable', 'integer', 'between:1,4096'],
                'almacenamiento_gb' => ['nullable', 'integer', 'between:1,1048576'],
            ], [
                'nombre_equipo.required' => 'Falta el nombre del equipo.',
                'direccion_mac.regex' => 'La dirección MAC no es válida.',
                'direccion_ip.ip' => 'La dirección IP no es válida.',
                '*.integer' => 'El campo :attribute debe ser un número entero.',
                '*.between' => 'El campo :attribute está fuera de rango.',
            ]);
            $errores = array_merge($errores, $validador->errors()->all());

            // Duplicados: en la base de datos (incluye eliminados) y dentro del archivo.
            $existente = null;
            foreach (['numero_inventario', 'numero_serie', 'direccion_mac'] as $clave) {
                $valor = $datos[$clave] ?? null;
                if ($valor === null) {
                    continue;
                }
                if (isset($vistos[$clave][$valor])) {
                    $errores[] = "El {$this->etiqueta($clave)} «{$valor}» se repite en la fila {$vistos[$clave][$valor]} del archivo.";
                }
                $vistos[$clave][$valor] = $numeroFila;
                $existente ??= Computadora::withTrashed()->where($clave, $valor)->first();
            }

            $estado = $errores !== [] ? 'error' : ($existente ? 'duplicado' : 'nueva');
            if ($existente?->trashed()) {
                $estado = 'error';
                $errores[] = "Coincide con el equipo eliminado «{$existente->nombre_equipo}»; restáuralo o corrige los identificadores.";
            }
            if ($estado === 'nueva' && $laboratorio === null && empty($crudo['laboratorio'])) {
                $avisos[] = 'Sin laboratorio: se registrará sin ubicación de laboratorio.';
            }

            $resultado[] = [
                'fila' => $numeroFila,
                'estado' => $estado,
                'nombre_equipo' => $datos['nombre_equipo'] ?? null,
                'numero_inventario' => $datos['numero_inventario'] ?? null,
                'laboratorio' => $laboratorio?->nombreCompleto(),
                'coincide_con' => $existente ? ['uuid' => $existente->uuid, 'nombre_equipo' => $existente->nombre_equipo] : null,
                'errores' => $errores,
                'avisos' => $avisos,
                '_datos' => $datos,
                '_software' => $instalaciones,
            ];
        }

        $conteo = fn (string $e) => count(array_filter($resultado, fn ($f) => $f['estado'] === $e));

        return [
            'filas' => $resultado,
            'resumen' => ['total' => count($resultado), 'nuevas' => $conteo('nueva'), 'duplicadas' => $conteo('duplicado'), 'con_errores' => $conteo('error')],
        ];
    }

    /**
     * @param array{confidencialidad:int, integridad:int, disponibilidad:int, departamento_id:?int, laboratorio_id:?int, duplicados:string} $opciones
     */
    public function importar(UploadedFile $archivo, array $opciones, Usuario $actor): array
    {
        $analisis = $this->analizar($archivo, $opciones['laboratorio_id'] ?? null);
        $actualizarDuplicados = ($opciones['duplicados'] ?? 'omitir') === 'actualizar';
        $creadas = $actualizadas = $omitidas = 0;
        $fallidas = [];

        DB::transaction(function () use ($analisis, $opciones, $actor, $actualizarDuplicados, &$creadas, &$actualizadas, &$omitidas, &$fallidas) {
            foreach ($analisis['filas'] as $fila) {
                $datos = array_filter($fila['_datos'], fn ($v) => $v !== null && $v !== '');
                try {
                    if ($fila['estado'] === 'error') {
                        $omitidas++;
                        continue;
                    }
                    if ($fila['estado'] === 'duplicado') {
                        if (!$actualizarDuplicados) {
                            $omitidas++;
                            continue;
                        }
                        $computadora = Computadora::query()->where('uuid', $fila['coincide_con']['uuid'])->firstOrFail();
                        $this->inventario->actualizarComputadora($computadora, $datos, $actor);
                        $actualizadas++;
                    } else {
                        $computadora = $this->inventario->crearComputadora($datos + [
                            'confidencialidad' => $opciones['confidencialidad'],
                            'integridad' => $opciones['integridad'],
                            'disponibilidad' => $opciones['disponibilidad'],
                            'departamento_id' => $opciones['departamento_id'] ?? null,
                            'controles_iso_ids' => $opciones['controles_iso_ids'] ?? [],
                        ], $actor);
                        $creadas++;
                    }
                    foreach ($fila['_software'] as $softwareId => $pivot) {
                        $previa = $computadora->softwareRegistrado()->whereKey($softwareId)->first();
                        if ($previa === null) {
                            $computadora->softwareRegistrado()->attach($softwareId, $pivot + ['estado_licencia' => 'pendiente_verificacion', 'estado_instalacion' => 'instalado']);
                        } elseif ($previa->pivot->estado_instalacion !== 'instalado') {
                            $computadora->softwareRegistrado()->updateExistingPivot($softwareId, $pivot + ['estado_instalacion' => 'instalado']);
                        }
                    }
                } catch (ValidationException $e) {
                    $fallidas[] = ['fila' => $fila['fila'], 'errores' => $e->validator->errors()->all()];
                }
            }
        });

        Bitacora::registrar('computadoras', 0, $actor->id, 'create', [
            'evento' => 'importacion_inventario',
            'archivo' => $archivo->getClientOriginalName(),
            'creadas' => $creadas, 'actualizadas' => $actualizadas, 'omitidas' => $omitidas, 'fallidas' => count($fallidas),
            'duplicados' => $actualizarDuplicados ? 'actualizar' : 'omitir',
        ]);

        return compact('creadas', 'actualizadas', 'omitidas', 'fallidas');
    }

    /** Lee CSV o XLSX y devuelve filas asociativas con los encabezados normalizados. */
    private function leer(UploadedFile $archivo): array
    {
        try {
            $libro = IOFactory::load($archivo->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo. Usa la plantilla en formato .xlsx o .csv (UTF-8).']);
        }
        $hoja = $libro->getSheet(0)->toArray(null, true, false, false);
        if (count($hoja) < 2) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene filas de datos.']);
        }

        $encabezados = array_map(fn ($h) => $this->clave((string) $h), array_shift($hoja));
        if (!in_array('nombre_equipo', $encabezados, true)) {
            throw ValidationException::withMessages(['archivo' => 'Falta la columna obligatoria «nombre_equipo». Descarga la plantilla para ver el formato.']);
        }
        $max = (int) config('sgsi.inventario.importacion_max_filas', 1000);
        $filas = [];
        foreach ($hoja as $fila) {
            if (count(array_filter($fila, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            $asociativa = [];
            foreach ($encabezados as $i => $clave) {
                if (in_array($clave, self::COLUMNAS, true)) {
                    $valor = $fila[$i] ?? null;
                    $asociativa[$clave] = is_string($valor) ? trim($valor) : $valor;
                }
            }
            $filas[] = $asociativa;
            if (count($filas) > $max) {
                throw ValidationException::withMessages(['archivo' => "El archivo supera el máximo de {$max} filas por importación."]);
            }
        }

        return $filas;
    }

    private function normalizar(array $crudo, array &$errores): array
    {
        $datos = [];
        foreach (self::COLUMNAS as $col) {
            if (in_array($col, ['edificio', 'laboratorio', 'software'], true)) {
                continue;
            }
            $valor = $crudo[$col] ?? null;
            $datos[$col] = ($valor === null || (is_string($valor) && $valor === '')) ? null : $valor;
        }
        foreach (['nombre_equipo', 'numero_inventario', 'numero_serie', 'direccion_ip', 'fabricante', 'modelo', 'procesador', 'so_nombre', 'so_edicion', 'so_version', 'antivirus_nombre', 'antivirus_fabricante', 'antivirus_version'] as $texto) {
            if ($datos[$texto] !== null) {
                $datos[$texto] = (string) $datos[$texto];
            }
        }
        if ($datos['direccion_mac'] !== null) {
            $limpia = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $datos['direccion_mac']));
            $datos['direccion_mac'] = strlen($limpia) === 12 ? implode(':', str_split($limpia, 2)) : (string) $datos['direccion_mac'];
        }
        foreach (['ram_gb', 'almacenamiento_gb'] as $num) {
            if ($datos[$num] !== null && is_numeric($datos[$num])) {
                $datos[$num] = (int) round((float) $datos[$num]);
            }
        }
        foreach (self::ENUMS as $col => $catalogo) {
            if ($datos[$col] === null) {
                unset($datos[$col]);
                continue;
            }
            $clave = $this->valorCatalogo((string) $datos[$col], $catalogo);
            if ($clave === null) {
                $errores[] = "Valor no reconocido en «{$col}»: «{$datos[$col]}».";
                unset($datos[$col]);
            } else {
                $datos[$col] = $clave;
            }
        }
        foreach (self::FECHAS as $col) {
            if ($datos[$col] === null) {
                continue;
            }
            $fecha = $this->fecha($datos[$col]);
            if ($fecha === null) {
                $errores[] = "Fecha no válida en «{$col}»: «{$datos[$col]}» (usa AAAA-MM-DD o DD/MM/AAAA).";
            }
            $datos[$col] = $fecha;
        }

        return $datos;
    }

    private function valorCatalogo(string $valor, array $catalogo): ?string
    {
        $buscado = $this->clave($valor);
        foreach ($catalogo as $clave => $etiqueta) {
            if ($buscado === $clave || $buscado === $this->clave($etiqueta)) {
                return $clave;
            }
        }

        return null;
    }

    private function fecha(mixed $valor): ?string
    {
        try {
            if (is_numeric($valor)) {
                return Carbon::instance(FechaExcel::excelToDateTimeObject((float) $valor))->toDateString();
            }
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
                $f = Carbon::createFromFormat('!' . $formato, (string) $valor);
                if ($f !== false && $f->format($formato) === (string) $valor) {
                    return $f->toDateString();
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function clave(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', '*' => '']);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $t), '_');
    }

    private function igual(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && $this->clave($a) === $this->clave($b);
    }

    private function etiqueta(string $clave): string
    {
        return ['numero_inventario' => 'número de inventario', 'numero_serie' => 'número de serie', 'direccion_mac' => 'dirección MAC'][$clave] ?? $clave;
    }
}
