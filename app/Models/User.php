<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'fuso_horario'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<Nota, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'usuario_id');
    }

    /** @return BelongsToMany<Nota, $this> */
    public function notasCompartilhadas(): BelongsToMany
    {
        return $this->belongsToMany(Nota::class, 'nota_participantes', 'usuario_id', 'nota_id')
            ->withPivot('papel')
            ->withTimestamps();
    }

    /** @return HasMany<Etiqueta, $this> */
    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class, 'usuario_id');
    }

    /** @return HasMany<Lembrete, $this> */
    public function lembretes(): HasMany
    {
        return $this->hasMany(Lembrete::class, 'usuario_id');
    }

    /** @return HasMany<NotificacaoInterna, $this> */
    public function notificacoesInternas(): HasMany
    {
        return $this->hasMany(NotificacaoInterna::class, 'usuario_id');
    }

    /** @return HasMany<OperacaoDesfazer, $this> */
    public function operacoesDesfazer(): HasMany
    {
        return $this->hasMany(OperacaoDesfazer::class, 'usuario_id');
    }

    /** @return HasMany<OperacaoSincronizacao, $this> */
    public function operacoesSincronizacao(): HasMany
    {
        return $this->hasMany(OperacaoSincronizacao::class, 'usuario_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
