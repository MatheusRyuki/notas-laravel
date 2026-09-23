<?php

namespace App\Http\Controllers;

use App\Models\Etiqueta;
use App\Models\Nota;
use App\Services\DesfazerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoteNotaController extends Controller
{
    public function __invoke(Request $request, DesfazerService $desfazer): RedirectResponse
    {
        $dados = $request->validate([
            'acao' => ['required', Rule::in(['arquivar', 'desarquivar', 'lixeira', 'restaurar', 'aplicar_etiqueta', 'remover_etiqueta'])],
            'notas' => ['required', 'array', 'min:1', 'max:100'],
            'notas.*' => ['integer', 'distinct'],
            'etiqueta_id' => ['nullable', 'integer'],
            'secao' => ['required', Rule::in(['ativas', 'arquivadas', 'lixeira'])],
        ], [
            'notas.required' => 'Selecione pelo menos uma nota.',
            'notas.min' => 'Selecione pelo menos uma nota.',
        ]);

        $ids = array_map('intval', $dados['notas']);
        $operacao = DB::transaction(function () use ($request, $dados, $ids, $desfazer) {
            $consulta = $dados['acao'] === 'restaurar'
                ? Nota::onlyTrashed()->where('usuario_id', $request->user()->id)
                : Nota::query()->whereIn('id', $ids);
            $notas = $consulta->whereIn('id', $ids)->lockForUpdate()->get();

            if ($notas->count() !== count($ids)) {
                throw ValidationException::withMessages(['notas' => 'O lote contém uma nota inexistente ou indisponível. Nenhuma alteração foi realizada.']);
            }

            $etiqueta = null;
            if (in_array($dados['acao'], ['aplicar_etiqueta', 'remover_etiqueta'], true)) {
                $etiqueta = Etiqueta::where('usuario_id', $request->user()->id)->find($dados['etiqueta_id']);
                if (! $etiqueta) {
                    throw ValidationException::withMessages(['etiqueta_id' => 'A etiqueta escolhida não pertence à sua conta.']);
                }
            }

            foreach ($notas as $nota) {
                $this->validarEstadoEPermissao($request, $nota, $dados['acao']);
            }

            $itensDesfazer = [];
            foreach ($notas as $nota) {
                if ($dados['acao'] === 'arquivar' || $dados['acao'] === 'desarquivar') {
                    $itensDesfazer[] = [
                        'nota_id' => $nota->id,
                        'arquivada_anterior' => $nota->arquivada,
                    ];
                    $nota->arquivada = $dados['acao'] === 'arquivar';
                    $nota->avancarRevisao();
                    $nota->save();
                    $itensDesfazer[array_key_last($itensDesfazer)]['revisao_esperada'] = $nota->revisao;
                } elseif ($dados['acao'] === 'lixeira') {
                    $nota->avancarRevisao();
                    $nota->save();
                    $nota->lembretes()->update(['ativo' => false, 'suspenso_lixeira' => true]);
                    $nota->delete();
                    $itensDesfazer[] = ['nota_id' => $nota->id, 'revisao_esperada' => $nota->revisao];
                } elseif ($dados['acao'] === 'restaurar') {
                    $nota->revisao++;
                    $nota->restore();
                    $nota->lembretes()->each(function ($lembrete): void {
                        if ($lembrete->agendado_em->isFuture()) {
                            $lembrete->forceFill(['ativo' => true, 'suspenso_lixeira' => false])->save();
                        }
                    });
                } else {
                    $existe = DB::table('etiqueta_nota')
                        ->where('usuario_id', $request->user()->id)
                        ->where('nota_id', $nota->id)
                        ->where('etiqueta_id', $etiqueta->id)
                        ->exists();
                    if ($dados['acao'] === 'aplicar_etiqueta' && ! $existe) {
                        DB::table('etiqueta_nota')->insert([
                            'usuario_id' => $request->user()->id,
                            'nota_id' => $nota->id,
                            'etiqueta_id' => $etiqueta->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    if ($dados['acao'] === 'remover_etiqueta') {
                        DB::table('etiqueta_nota')
                            ->where('usuario_id', $request->user()->id)
                            ->where('nota_id', $nota->id)
                            ->where('etiqueta_id', $etiqueta->id)
                            ->delete();
                    }
                }
            }

            if ($itensDesfazer === []) {
                return null;
            }

            return $desfazer->registrar(
                $request->user(),
                $dados['acao'] === 'lixeira' ? 'lixeira' : 'arquivamento',
                $itensDesfazer,
                ['rota' => $this->rotaSecao($dados['secao']), 'consulta' => $this->consulta($request)],
            );
        }, 3);

        $resposta = redirect()->route($this->rotaSecao($dados['secao']), $this->consulta($request))
            ->with('sucesso', 'Ação aplicada a '.count($ids).' notas.');
        if ($operacao) {
            $resposta->with('desfazer', $operacao->token);
        }

        return $resposta;
    }

    private function validarEstadoEPermissao(Request $request, Nota $nota, string $acao): void
    {
        if (in_array($acao, ['aplicar_etiqueta', 'remover_etiqueta'], true)) {
            if ($nota->trashed() || ! $request->user()->can('view', $nota)) {
                throw ValidationException::withMessages(['notas' => 'Uma nota não permite alterar etiquetas. Nenhuma alteração foi realizada.']);
            }

            return;
        }

        $habilidade = $acao === 'restaurar' ? 'restore' : 'manageState';
        if (! $request->user()->can($habilidade, $nota)) {
            throw ValidationException::withMessages(['notas' => 'Uma nota não permite esta ação. Nenhuma alteração foi realizada.']);
        }

        $estadoValido = match ($acao) {
            'arquivar' => ! $nota->arquivada,
            'desarquivar' => $nota->arquivada,
            'lixeira' => ! $nota->trashed(),
            'restaurar' => $nota->trashed(),
            default => true,
        };

        if (! $estadoValido) {
            throw ValidationException::withMessages(['notas' => 'Uma nota não está no estado esperado. Nenhuma alteração foi realizada.']);
        }
    }

    private function rotaSecao(string $secao): string
    {
        return match ($secao) {
            'arquivadas' => 'notas.arquivadas',
            'lixeira' => 'lixeira.index',
            default => 'notas.inicio',
        };
    }

    private function consulta(Request $request): array
    {
        return collect($request->only(['q', 'ordem', 'etiquetas']))
            ->reject(fn ($valor) => $valor === null || $valor === '' || $valor === [])
            ->all();
    }
}
