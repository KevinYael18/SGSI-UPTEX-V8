<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NuevaVersionDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('documento'));
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,xlsm'],
            'dias_vigencia' => ['nullable', 'integer', 'min:1'],
        ];
    }
}