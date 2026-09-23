<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['tipo', 'dados', 'expira_em'])]
class OperacaoDesfazer extends Model
{
    protected $table = 'operacoes_desfazer';

    protected static function booted(): void
    {
        static::creating(fn (OperacaoDesfazer $operacao) => $operacao->token ??= (string) Str::uuid());
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return ['dados' => 'array', 'expira_em' => 'datetime', 'utilizada_em' => 'datetime'];
    }
}
