<?php

namespace App\Models;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\PapelNota;
use App\Enums\TipoAparencia;
use App\Enums\TipoNota;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['titulo', 'descricao', 'tipo_conteudo', 'uuid_sincronizacao'])]
class Nota extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'tipo_conteudo' => 'texto',
        'revisao' => 1,
    ];

    protected $table = 'notas';

    protected static function booted(): void
    {
        static::creating(function (Nota $nota): void {
            $nota->uuid_sincronizacao ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasMany<NotaItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(NotaItem::class)->orderBy('posicao');
    }

    /** @return BelongsToMany<Etiqueta, $this> */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'etiqueta_nota')
            ->withPivot('usuario_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'nota_participantes', 'nota_id', 'usuario_id')
            ->withPivot('papel')
            ->withTimestamps();
    }

    /** @return HasMany<ConviteNota, $this> */
    public function convites(): HasMany
    {
        return $this->hasMany(ConviteNota::class);
    }

    /** @return HasMany<Lembrete, $this> */
    public function lembretes(): HasMany
    {
        return $this->hasMany(Lembrete::class);
    }

    public function fundo(): ?FundoNota
    {
        return FundoNota::porCaminho($this->caminho_imagem);
    }

    public function pertenceA(User $usuario): bool
    {
        return $this->usuario_id === $usuario->id;
    }

    public function papelDe(User $usuario): ?PapelNota
    {
        if ($this->pertenceA($usuario)) {
            return null;
        }

        $papel = $this->participantes()
            ->whereKey($usuario->id)
            ->value('nota_participantes.papel');

        return $papel ? PapelNota::from($papel) : null;
    }

    public function podeEditar(User $usuario): bool
    {
        return $this->pertenceA($usuario) || $this->papelDe($usuario) === PapelNota::Editor;
    }

    /** @param Builder<Nota> $query */
    public function scopeAcessiveisPor(Builder $query, User $usuario): Builder
    {
        return $query->where(function (Builder $acesso) use ($usuario): void {
            $acesso->where('usuario_id', $usuario->id)
                ->orWhereHas('participantes', fn (Builder $participantes) => $participantes->whereKey($usuario->id));
        });
    }

    public function avancarRevisao(): void
    {
        $this->revisao++;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fixada' => 'boolean',
            'arquivada' => 'boolean',
            'cor' => CorNota::class,
            'tipo_aparencia' => TipoAparencia::class,
            'tipo_conteudo' => TipoNota::class,
            'revisao' => 'integer',
        ];
    }
}
