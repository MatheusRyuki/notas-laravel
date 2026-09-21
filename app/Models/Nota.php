<?php

namespace App\Models;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['titulo', 'descricao'])]
class Nota extends Model
{
    use SoftDeletes;

    protected $table = 'notas';

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function fundo(): ?FundoNota
    {
        return FundoNota::porCaminho($this->caminho_imagem);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fixada' => 'boolean',
            'arquivada' => 'boolean',
            'cor' => CorNota::class,
            'tipo_aparencia' => TipoAparencia::class,
        ];
    }
}
