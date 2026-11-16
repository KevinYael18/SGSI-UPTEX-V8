<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaFragmentoDocumento extends Model
{
    protected $table = 'ia_fragmentos_documento';

    protected $fillable = ['documento_id', 'orden', 'contenido', 'embedding', 'modelo_embedding', 'sospechoso'];

    protected $casts = ['sospechoso' => 'boolean'];

    protected $hidden = ['embedding'];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(IaDocumentoConocimiento::class, 'documento_id');
    }

    /** @return list<float>|null */
    public function vector(): ?array
    {
        if (blank($this->embedding)) {
            return null;
        }
        $binario = base64_decode($this->embedding, true);

        return $binario === false ? null : array_values(unpack('g*', $binario));
    }

    /** @param list<float> $vector */
    public static function empaquetar(array $vector): string
    {
        return base64_encode(pack('g*', ...$vector));
    }
}
