<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fotografía de perfil. Las reglas `mimes` y `mimetypes` de Laravel
 * inspeccionan el CONTENIDO real del archivo (finfo), no la extensión que
 * envía el cliente. Después, PerfilService vuelve a decodificar y recodificar
 * la imagen con GD, de modo que lo que se almacena nunca es el archivo original.
 */
class SubirFotoPerfilRequest extends FormRequest
{
    public const MAX_KB = 2048;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'foto' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required' => 'Selecciona una imagen.',
            'foto.file' => 'El archivo no es válido.',
            'foto.uploaded' => 'No se pudo subir el archivo. Verifica que no exceda 2 MB.',
            'foto.max' => 'La imagen no puede pesar más de 2 MB.',
            'foto.mimes' => 'Formato no permitido. Usa JPG, JPEG, PNG o WEBP.',
            'foto.mimetypes' => 'El contenido del archivo no corresponde a una imagen JPG, PNG o WEBP.',
            'foto.dimensions' => 'La imagen debe medir entre 64×64 y 4096×4096 píxeles.',
        ];
    }
}
