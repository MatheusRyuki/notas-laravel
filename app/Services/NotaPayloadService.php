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

        $tipoAparencia = TipoAparencia::from($dados['tipo_aparencia']);
        if ($tipoAparencia === TipoAparencia::Imagem) {
            $fundo = FundoNota::from($dados['fundo']);
            $nota->forceFill([
                'tipo_aparencia' => $tipoAparencia,
                'cor' => null,
                'caminho_imagem' => $fundo->caminho(),
            ]);
        } else {
            $cor = CorNota::from($dados['cor']);
            $nota->forceFill([
                'tipo_aparencia' => $tipoAparencia,
                'cor' => $cor === CorNota::Padrao ? null : $cor,
                'caminho_imagem' => null,
            ]);
        }

        $nota->save();
        if ($nota->tipo_conteudo === TipoNota::Lista) {
            $nota->itens()->update(['posicao' => DB::raw('posicao + 1000')]);
            $ids = [];
            foreach (array_values($dados['itens']) as $posicao => $itemDados) {
                $item = $nota->itens()->make([
                    'texto' => $itemDados['texto'],
                    'concluido' => $itemDados['concluido'],
                    'posicao' => $posicao,
                ]);
                $item->save();
                $ids[] = $item->id;
            }
            $nota->itens()->whereNotIn('id', $ids)->delete();
        } else {
            $nota->itens()->delete();
        }
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
