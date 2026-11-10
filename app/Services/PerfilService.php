<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\PerfilActualizado;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lógica del módulo «Mi perfil». Siempre opera sobre el usuario que recibe
 * (el controlador le pasa $request->user()); no existe forma de indicar otro
 * usuario desde la API.
 */
class PerfilService
{
    public const DISCO = 'public';
    public const CARPETA = 'fotos-perfil';
    private const LADO_PX = 512;

    public function actualizarDatos(Usuario $usuario, array $datos): Usuario
    {
        $antes = ['nombre' => $usuario->nombre];

        // forceFill con lista blanca explícita: el controlador ya solo pasa
        // campos validados, y aquí se vuelve a filtrar por defensa en profundidad.
        $usuario->forceFill(['nombre' => $datos['nombre']]);

        if ($usuario->isDirty()) {
            $usuario->save();
            event(new PerfilActualizado($usuario, ['antes' => $antes, 'despues' => ['nombre' => $usuario->nombre]]));
        }

        return $usuario;
    }

    /**
     * Decodifica la imagen con GD, la recorta al centro en cuadrado, la
     * redimensiona a 512×512 y la vuelve a codificar (WebP, o JPEG si el
     * servidor no tuviera WebP). Así se descartan metadatos EXIF (p. ej. GPS)
     * y cualquier contenido no-imagen incrustado en el archivo original.
     */
    public function guardarFoto(Usuario $usuario, UploadedFile $archivo): Usuario
    {
        $binario = $this->recodificar($archivo);
        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $ruta = self::CARPETA . '/' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk(self::DISCO)->put($ruta, $binario, 'public');

        $anterior = $usuario->foto_perfil_path;

        try {
            DB::transaction(function () use ($usuario, $ruta) {
                $usuario->forceFill(['foto_perfil_path' => $ruta])->save();
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }

        $this->borrarArchivo($anterior);
        event(new PerfilActualizado($usuario, ['foto_perfil' => $anterior ? 'reemplazada' : 'agregada']));

        return $usuario;
    }

    public function eliminarFoto(Usuario $usuario): Usuario
    {
        $anterior = $usuario->foto_perfil_path;
        if ($anterior === null) {
            return $usuario;
        }

        $usuario->forceFill(['foto_perfil_path' => null])->save();
        $this->borrarArchivo($anterior);
        event(new PerfilActualizado($usuario, ['foto_perfil' => 'eliminada']));

        return $usuario;
    }

    /**
     * Cambia la contraseña (hash Bcrypt/Argon según config de Laravel) e
     * invalida las DEMÁS sesiones del usuario y sus tokens personales.
     * La sesión actual se conserva (se regenera su ID en el controlador).
     */
    public function cambiarPassword(Usuario $usuario, string $nueva, ?string $idSesionActual): int
    {
        $usuario->forceFill(['password_hash' => Hash::make($nueva)])->save();

        $cerradas = 0;
        if (config('session.driver') === 'database') {
            $cerradas = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $usuario->id)
                ->when($idSesionActual, fn ($q) => $q->where('id', '!=', $idSesionActual))
                ->delete();
        }

        $usuario->tokens()->delete();

        event(new PerfilActualizado($usuario, ['password' => 'cambiada', 'sesiones_cerradas' => $cerradas]));

        return $cerradas;
    }

    public static function urlPublica(?string $ruta): ?string
    {
        // Ruta relativa: funciona igual detrás del proxy, en cualquier host o puerto.
        return $ruta ? '/storage/' . ltrim($ruta, '/') : null;
    }

    private function borrarArchivo(?string $ruta): void
    {
        // Solo se borra dentro de la carpeta de fotos (nunca rutas arbitrarias).
        if ($ruta && str_starts_with($ruta, self::CARPETA . '/') && !str_contains($ruta, '..')) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    private function recodificar(UploadedFile $archivo): string
    {
        $info = @getimagesize($archivo->getRealPath());
        $tiposPermitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if ($info === false || !in_array($info[2], $tiposPermitidos, true)) {
            throw ValidationException::withMessages(['foto' => 'El archivo no es una imagen JPG, PNG o WEBP válida.']);
        }

        $origen = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($archivo->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($archivo->getRealPath()) : false,
        };

        if (!$origen) {
            throw ValidationException::withMessages(['foto' => 'No se pudo procesar la imagen. Intenta con otro archivo.']);
        }

        $origen = $this->corregirOrientacion($origen, $archivo, $info[2]);

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);
        $x = intdiv($ancho - $lado, 2);
        $y = intdiv($alto - $lado, 2);
        $destinoLado = min(self::LADO_PX, $lado);

        $destino = imagecreatetruecolor($destinoLado, $destinoLado);
        // Fondo blanco para PNG/WEBP con transparencia.
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origen, 0, 0, $x, $y, $destinoLado, $destinoLado, $lado, $lado);

        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($destino, null, 85) : imagejpeg($destino, null, 88);
        $binario = (string) ob_get_clean();

        imagedestroy($origen);
        imagedestroy($destino);

        if (!$ok || $binario === '') {
            throw ValidationException::withMessages(['foto' => 'No se pudo procesar la imagen. Intenta con otro archivo.']);
        }

        return $binario;
    }

    /** Respeta la orientación EXIF de fotos tomadas con celular (solo JPEG). */
    private function corregirOrientacion(\GdImage $imagen, UploadedFile $archivo, int $tipo): \GdImage
    {
        if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($archivo->getRealPath());
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $rotada = imagerotate($imagen, $grados, 0);
        if ($rotada === false) {
            return $imagen;
        }
        imagedestroy($imagen);

        return $rotada;
    }
}
