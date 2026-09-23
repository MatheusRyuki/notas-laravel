<?php

namespace App\Models;

use App\Enums\PapelNota;
use App\Enums\StatusConvite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['convidado_id', 'convidado_por', 'email_destino', 'papel', 'status'])]
class ConviteNota extends Model
{
    protected $table = 'convites_notas';

    protected static function booted(): void
    {
        static::creating(fn (ConviteNota $convite) => $convite->token ??= (string) Str::uuid());
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    public function convidado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'convidado_id');
    }

    protected function casts(): array
    {
        return [
            'papel' => PapelNota::class,
            'status' => StatusConvite::class,
            'respondido_em' => 'datetime',
        ];
    }
}
