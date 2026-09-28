<?php

namespace App\Services;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Enums\TipoNota;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class NotaPayloadService
{
    public function validar(array $payload, ?Nota $nota = null): array
    {
        $tipo = $nota?->tipo_conteudo->value ?? ($payload['tipo_conteudo'] ?? TipoNota::Texto->value);
        $regras = [
            'titulo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:10000'],
            'tipo_conteudo' => ['required', $nota ? Rule::in([$tipo]) : new Enum(TipoNota::class)],
            'itens' => [$tipo === TipoNota::Lista->value ? 'required' : 'nullable', 'array', 'max:100'],
            'itens.*.id' => ['nullable', 'integer', 'distinct'],
            'itens.*.texto' => ['required', 'string', 'max:500'],
            'itens.*.concluido' => ['nullable', 'boolean'],
            'tipo_aparencia' => ['required', new Enum(TipoAparencia::class)],
            'cor' => ['nullable', 'required_if:tipo_aparencia,cor', new Enum(CorNota::class)],
            'fundo' => ['nullable', 'required_if:tipo_aparencia,imagem', new Enum(FundoNota::class)],
        ];

        $dados = Validator::make($payload, $regras)->validate();
        $dados['titulo'] = $this->limpar($dados['titulo'] ?? null);
        $dados['descricao'] = $this->limpar($dados['descricao'] ?? null);
        $dados['itens'] = collect($dados['itens'] ?? [])->map(fn ($item) => [
            'id' => $item['id'] ?? null,
            'texto' => $this->limpar($item['texto'] ?? null),
            'concluido' => (bool) ($item['concluido'] ?? false),
        ])->filter(fn ($item) => $item['texto'] !== null)->values()->all();

        if ($tipo === TipoNota::Texto->value && $dados['titulo'] === null && $dados['descricao'] === null) {
            Validator::make([], ['conteudo' => ['required']], ['conteudo.required' => 'Informe um título ou uma descrição.'])->validate();
        }
        if ($tipo === TipoNota::Lista->value && $dados['itens'] === []) {
            Validator::make([], ['itens' => ['required']], ['itens.required' => 'Adicione pelo menos um item à lista.'])->validate();
        }

        return $dados;
    }

    public function aplicar(Nota $nota, array $dados): void
    {
        $nota->fill([
            'titulo' => $dados['titulo'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'tipo_conteudo' => $dados['tipo_conteudo'],
        ]);

        if (array_key_exists('tipo_aparencia', $dados)) {
            $this->aplicarAparencia($nota, $dados);
        }
        $nota->save();
        $this->salvarItens($nota, $dados['itens'] ?? []);
    }

    private function aplicarAparencia(Nota $nota, array $dados): void
    {
        $tipo = TipoAparencia::from($dados['tipo_aparencia']);
        if ($tipo === TipoAparencia::Imagem) {
            $fundo = FundoNota::from($dados['fundo']);
            $nota->forceFill(['tipo_aparencia' => $tipo, 'cor' => null, 'caminho_imagem' => $fundo->caminho()]);

            return;
        }

        $cor = CorNota::from($dados['cor']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Cor,
            'cor' => $cor === CorNota::Padrao ? null : $cor,
            'caminho_imagem' => null,
        ]);
    }

    private function salvarItens(Nota $nota, array $itens): void
    {
        if ($nota->tipo_conteudo !== TipoNota::Lista) {
            $nota->itens()->delete();

            return;
        }

        $nota->itens()->update(['posicao' => DB::raw('posicao + 1000')]);
        $idsMantidos = [];
        foreach (array_values($itens) as $posicao => $dados) {
            $item = isset($dados['id'])
                ? $nota->itens()->whereKey($dados['id'])->first()
                : null;
            $item ??= $nota->itens()->make();
            $item->fill([
                'texto' => $dados['texto'],
                'concluido' => (bool) ($dados['concluido'] ?? false),
                'posicao' => $posicao,
            ]);
            $item->save();
            $idsMantidos[] = $item->id;
        }

        $nota->itens()->whereNotIn('id', $idsMantidos)->delete();
    }

    public function representar(Nota $nota, User $usuario): array
    {
        $nota->loadMissing(['itens', 'usuario']);
        $papel = $nota->pertenceA($usuario) ? 'proprietario' : $nota->papelDe($usuario)?->value;

        return [
            'id' => $nota->id,
            'uuid' => $nota->uuid_sincronizacao,
            'titulo' => $nota->titulo,
            'descricao' => $nota->descricao,
            'tipo_conteudo' => $nota->tipo_conteudo->value,
            'itens' => $nota->itens->map(fn ($item) => [
                'id' => $item->id,
                'texto' => $item->texto,
                'concluido' => $item->concluido,
                'posicao' => $item->posicao,
            ])->values()->all(),
            'tipo_aparencia' => $nota->tipo_aparencia->value,
            'cor' => $nota->cor?->value ?? CorNota::Padrao->value,
            'fundo' => $nota->fundo()?->value,
            'fixada' => $nota->fixada,
            'arquivada' => $nota->arquivada,
            'revisao' => $nota->revisao,
            'papel' => $papel,
            'proprietario' => $nota->usuario->name,
            'pode_editar' => $nota->podeEditar($usuario),
            'atualizada_em' => $nota->updated_at?->toISOString(),
        ];
    }

    private function limpar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
