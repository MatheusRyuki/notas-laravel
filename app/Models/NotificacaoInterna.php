<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'nota_id', 'tipo', 'chave', 'dados', 'lida_em'])]
class NotificacaoInterna extends Model
{
    protected $table = 'notificacoes_internas';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    protected function casts(): array
    {
        return ['dados' => 'array', 'lida_em' => 'datetime'];
    }
}
