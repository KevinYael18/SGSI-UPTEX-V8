<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\IaDocumentoConocimiento;
use App\Services\AI\AIKnowledgeBase;
use Illuminate\Foundation\Http\FormRequest;

class IaConocimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ia.conocimiento');
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'extensions:' . implode(',', AIKnowledgeBase::extensionesPermitidas()), 'max:' . (int) config('sgsi.ia.rag.max_archivo_kb')],
            'titulo' => ['required', 'string', 'max:200'],
            'categoria' => ['required', 'string', 'in:' . implode(',', IaDocumentoConocimiento::CATEGORIAS)],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.extensions' => 'Formato no permitido. Use PDF, DOCX, XLSX, TXT, MD o CSV.',
            'archivo.max' => 'El archivo excede el tamaño máximo permitido.',
        ];
    }
}
