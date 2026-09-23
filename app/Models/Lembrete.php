<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'agendado_em', 'fuso_horario', 'enviar_email', 'ativo', 'suspenso_lixeira', 'processado_em'])]
class Lembrete extends Model
{
    protected $table = 'lembretes';

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return [
            'agendado_em' => 'datetime',
            'enviar_email' => 'boolean',
            'ativo' => 'boolean',
            'suspenso_lixeira' => 'boolean',
            'processado_em' => 'datetime',
        ];
    }
}
