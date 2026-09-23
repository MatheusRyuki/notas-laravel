<?php

namespace App\Http\Controllers;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Enums\TipoNota;
use App\Http\Requests\ArquivarNotaRequest;
use App\Http\Requests\BuscarNotasRequest;
use App\Http\Requests\FixarNotaRequest;
use App\Http\Requests\StoreNotaRequest;
use App\Http\Requests\UpdateNotaRequest;
use App\Models\Nota;
use App\Services\DesfazerService;
use App\Support\BuscaNotas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotaController extends Controller
{
    public function index(BuscarNotasRequest $request): View|JsonResponse
    {
        return $this->listagem($request, false);
    }

    public function arquivadas(BuscarNotasRequest $request): View|JsonResponse
    {
        return $this->listagem($request, true);
    }

    public function store(StoreNotaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $nota = new Nota;
            $nota->fill($request->safe()->only(['titulo', 'descricao', 'tipo_conteudo']));
            $this->aplicarAparencia($nota, $request->validated());
            $request->user()->notas()->save($nota);
            $this->salvarItens($nota, $request->validated('itens', []));
        });

        return redirect()->route('notas.inicio', $this->consultaRetorno($request))
            ->with('sucesso', 'Nota criada com sucesso.');
    }

    public function show(Request $request, Nota $nota): JsonResponse
    {
        Gate::authorize('view', $nota);
        $nota->load(['itens', 'usuario']);

        return response()->json($this->representarNota($nota, $request));
    }

    public function update(UpdateNotaRequest $request, Nota $nota): JsonResponse
    {
        $dados = $request->validated();

        $resultado = DB::transaction(function () use ($request, $nota, $dados): Nota|JsonResponse {
            $atual = Nota::query()->whereKey($nota->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $atual);

            if ($atual->revisao !== (int) $dados['revisao']) {
                $atual->load(['itens', 'usuario']);

                return response()->json([
                    'mensagem' => 'A nota foi alterada em outra sessão. Escolha qual versão deseja manter.',
                    'conflito' => [
                        'local' => $request->safe()->only(['titulo', 'descricao', 'tipo_conteudo', 'itens', 'tipo_aparencia', 'cor', 'fundo']),
                        'atual' => $this->representarNota($atual, $request),
                    ],
                ], 409);
            }

            $atual->fill($request->safe()->only(['titulo', 'descricao']));
            if (array_key_exists('tipo_aparencia', $dados)) {
                $this->aplicarAparencia($atual, $dados);
            }
            $this->salvarItens($atual, $dados['itens'] ?? []);
            $atual->avancarRevisao();
            $atual->save();

            return $atual;
        }, 3);

        if ($resultado instanceof JsonResponse) {
            return $resultado;
        }

        $request->session()->flash('sucesso', 'Nota atualizada com sucesso.');
        $resultado->load(['itens', 'usuario']);

        return response()->json([
            'mensagem' => 'Nota atualizada com sucesso.',
            'nota' => $this->representarNota($resultado, $request),
        ]);
    }

    public function fixacao(FixarNotaRequest $request, Nota $nota): RedirectResponse
    {
        $desejado = $request->boolean('fixada');

        DB::transaction(function () use ($nota, $desejado): void {
            $atual = Nota::query()->whereKey($nota->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageState', $atual);

            if ($atual->fixada !== $desejado) {
                $atual->forceFill(['fixada' => $desejado]);
                $atual->avancarRevisao();
                $atual->save();
            }
        }, 3);

        return redirect()->route('notas.inicio', $this->consultaRetorno($request))
            ->with('sucesso', $desejado ? 'Nota fixada com sucesso.' : 'Nota desafixada com sucesso.');
    }

    public function arquivamento(ArquivarNotaRequest $request, Nota $nota, DesfazerService $desfazer): RedirectResponse
    {
        $desejado = $request->boolean('arquivada');
        $operacao = null;

        DB::transaction(function () use ($request, $nota, $desejado, $desfazer, &$operacao): void {
            $atual = Nota::query()->whereKey($nota->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageState', $atual);

            if ($atual->arquivada === $desejado) {
                return;
            }

            $anterior = $atual->arquivada;
            $atual->arquivada = $desejado;
            $atual->avancarRevisao();
            $atual->save();

            $operacao = $desfazer->registrar($request->user(), 'arquivamento', [[
                'nota_id' => $atual->id,
                'arquivada_anterior' => $anterior,
                'revisao_esperada' => $atual->revisao,
            ]], [
                'rota' => $desejado ? 'notas.inicio' : 'notas.arquivadas',
                'consulta' => $this->consultaRetorno($request),
            ]);
        });

        $rota = $desejado ? 'notas.inicio' : 'notas.arquivadas';
        $mensagem = $desejado ? 'Nota arquivada com sucesso.' : 'Nota desarquivada com sucesso.';

        $resposta = redirect()->route($rota, $this->consultaRetorno($request))->with('sucesso', $mensagem);
        if ($operacao) {
            $resposta->with('desfazer', $operacao->token);
        }

        return $resposta;
    }

    public function moverLixeira(Request $request, Nota $nota, DesfazerService $desfazer): RedirectResponse
    {
        Gate::authorize('delete', $nota);
        $rotaRetorno = $nota->arquivada ? 'notas.arquivadas' : 'notas.inicio';
        $operacao = DB::transaction(function () use ($request, $nota, $desfazer, $rotaRetorno) {
            $atual = Nota::query()->whereKey($nota->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $atual);
            $atual->avancarRevisao();
            $atual->save();
            $atual->lembretes()->update(['ativo' => false, 'suspenso_lixeira' => true]);
            $atual->delete();

            return $desfazer->registrar($request->user(), 'lixeira', [[
                'nota_id' => $atual->id,
                'revisao_esperada' => $atual->revisao,
            ]], [
                'rota' => $rotaRetorno,
                'consulta' => $this->consultaRetorno($request),
            ]);
        });

        return redirect()->route($rotaRetorno, $this->consultaRetorno($request))
            ->with('sucesso', 'Nota movida para a lixeira.')
            ->with('desfazer', $operacao->token);
    }

    private function listagem(BuscarNotasRequest $request, bool $arquivadas): View|JsonResponse
    {
        $dadosValidados = $request->validated();
        $termo = $dadosValidados['q'] ?? null;
        $ordem = $dadosValidados['ordem'] ?? 'atualizacao';
        $etiquetasSelecionadas = $this->validarEtiquetas($request, $dadosValidados['etiquetas'] ?? []);
        $secao = $arquivadas ? 'arquivadas' : 'ativas';

        $consulta = Nota::query()
            ->acessiveisPor($request->user())
            ->where('arquivada', $arquivadas)
            ->with([
                'itens',
                'usuario:id,name',
                'etiquetas' => fn ($q) => $q->where('etiqueta_nota.usuario_id', $request->user()->id),
            ]);

        BuscaNotas::aplicar($consulta, $termo);

        if ($etiquetasSelecionadas !== []) {
            $consulta->whereHas('etiquetas', fn (Builder $q) => $q
                ->where('etiqueta_nota.usuario_id', $request->user()->id)
                ->whereIn('etiquetas.id', $etiquetasSelecionadas));
        }

        $this->aplicarOrdenacao($consulta, $ordem);
        $notas = $consulta->get();

        $dados = [
            'notas' => $notas,
            'notasFixadas' => $notas->where('fixada', true),
            'outrasNotas' => $notas->where('fixada', false),
            'secaoArquivadas' => $arquivadas,
            'secao' => $secao,
            'termo' => $termo,
            'ordem' => $ordem,
            'etiquetas' => $request->user()->etiquetas()->orderBy('nome_normalizado')->get(),
            'etiquetasSelecionadas' => $etiquetasSelecionadas,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'secao' => $secao,
                'termo' => $termo ?? '',
                'ordem' => $ordem,
                'etiquetas' => $etiquetasSelecionadas,
                'total' => $notas->count(),
                'html' => view('notas._resultados', $dados)->render(),
            ]);
        }

        return view('notas.inicio', $dados);
    }

    private function aplicarOrdenacao(Builder $consulta, string $ordem): void
    {
        $consulta->orderByDesc('fixada');

        match ($ordem) {
            'criacao' => $consulta->orderByDesc('created_at')->orderByDesc('id'),
            'titulo' => $consulta
                ->orderByRaw("CASE WHEN titulo IS NULL OR titulo = '' THEN 1 ELSE 0 END")
                ->orderByRaw('LOWER(titulo) ASC')
                ->orderBy('id'),
            default => $consulta->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    private function validarEtiquetas(Request $request, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $validas = $request->user()->etiquetas()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($validas) !== count($ids)) {
            throw ValidationException::withMessages(['etiquetas' => 'Uma das etiquetas selecionadas não pertence à sua conta.']);
        }

        return $validas;
    }

    private function consultaRetorno(Request $request): array
    {
        return collect($request->only(['q', 'ordem', 'etiquetas']))
            ->reject(fn ($valor) => $valor === null || $valor === '' || $valor === [])
            ->all();
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

    private function representarNota(Nota $nota, Request $request): array
    {
        $papel = $nota->pertenceA($request->user()) ? 'proprietario' : $nota->papelDe($request->user())?->value;

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
            ])->values(),
            'tipo_aparencia' => $nota->tipo_aparencia->value,
            'cor' => $nota->cor?->value ?? CorNota::Padrao->value,
            'fundo' => $nota->fundo()?->value,
            'fixada' => $nota->fixada,
            'arquivada' => $nota->arquivada,
            'revisao' => $nota->revisao,
            'papel' => $papel,
            'proprietario' => $nota->usuario->name,
            'pode_editar' => $request->user()->can('update', $nota),
            'url_atualizacao' => route('notas.update', $nota),
        ];
    }
}
