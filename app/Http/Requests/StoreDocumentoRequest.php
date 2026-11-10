<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Documento::class);
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'archivo' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,xlsm'],
            'dias_vigencia' => ['nullable', 'integer', 'min:1'],
        ];
    }
}