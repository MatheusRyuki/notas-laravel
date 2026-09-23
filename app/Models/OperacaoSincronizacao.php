<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operacao_uuid', 'acao', 'id_local', 'resultado'])]
class OperacaoSincronizacao extends Model
{
    protected $table = 'operacoes_sincronizacao';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return ['resultado' => 'array'];
    }
}
