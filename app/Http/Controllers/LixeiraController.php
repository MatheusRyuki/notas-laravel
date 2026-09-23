<?php

namespace App\Http\Controllers;

use App\Enums\CorNota;
use App\Http\Requests\BuscarNotasRequest;
use App\Models\Nota;
use App\Support\BuscaNotas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LixeiraController extends Controller
{
    public function index(BuscarNotasRequest $request): View|JsonResponse
    {
        $dadosValidados = $request->validated();
        $termo = $dadosValidados['q'] ?? null;
        $ordem = $dadosValidados['ordem'] ?? 'atualizacao';
        $etiquetasSelecionadas = $this->validarEtiquetas($request, $dadosValidados['etiquetas'] ?? []);

        $consulta = $request->user()->notas()->onlyTrashed()->with([
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

        match ($ordem) {
            'criacao' => $consulta->orderByDesc('created_at')->orderByDesc('id'),
            'titulo' => $consulta
                ->orderByRaw("CASE WHEN titulo IS NULL OR titulo = '' THEN 1 ELSE 0 END")
                ->orderByRaw('LOWER(titulo) ASC')
                ->orderBy('id'),
            default => $consulta->orderByDesc('deleted_at')->orderByDesc('id'),
        };
        $notas = $consulta->get();

        $dados = [
            'notas' => $notas,
            'secao' => 'lixeira',
            'termo' => $termo,
            'ordem' => $ordem,
            'etiquetas' => $request->user()->etiquetas()->orderBy('nome_normalizado')->get(),
            'etiquetasSelecionadas' => $etiquetasSelecionadas,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'secao' => 'lixeira',
                'termo' => $termo ?? '',
                'ordem' => $ordem,
                'etiquetas' => $etiquetasSelecionadas,
                'total' => $notas->count(),
                'html' => view('notas._resultados', $dados)->render(),
            ]);
        }

        return view('lixeira.index', $dados);
    }

    public function show(Request $request, int $nota): JsonResponse
    {
        $registro = $this->notaRemovida($nota);
        Gate::authorize('view', $registro);
        $registro->load('itens');

        return response()->json([
            'id' => $registro->id,
            'titulo' => $registro->titulo,
            'descricao' => $registro->descricao,
            'tipo_conteudo' => $registro->tipo_conteudo->value,
            'itens' => $registro->itens->map(fn ($item) => [
                'texto' => $item->texto,
                'concluido' => $item->concluido,
                'posicao' => $item->posicao,
            ])->values(),
            'tipo_aparencia' => $registro->tipo_aparencia->value,
            'cor' => $registro->cor?->value ?? CorNota::Padrao->value,
            'fundo' => $registro->fundo()?->value,
            'fixada' => $registro->fixada,
            'arquivada' => $registro->arquivada,
            'revisao' => $registro->revisao,
        ]);
    }

    public function confirmarExclusao(Request $request, int $nota): View
    {
        $registro = $this->notaRemovida($nota);
        Gate::authorize('forceDelete', $registro);

        return view('lixeira.confirmar-exclusao', [
            'nota' => $registro,
            'consulta' => $this->consultaRetorno($request),
        ]);
    }

    public function restaurar(Request $request, int $nota): RedirectResponse
    {
        $registro = $this->notaRemovida($nota);
        Gate::authorize('restore', $registro);
        $destino = $registro->arquivada ? 'Arquivadas' : 'Minhas notas';

        DB::transaction(function () use ($registro): void {
            $atual = Nota::onlyTrashed()->whereKey($registro->id)->lockForUpdate()->firstOrFail();
            $atual->revisao++;
            $atual->restore();
            $atual->lembretes()->each(function ($lembrete): void {
                if ($lembrete->agendado_em->isFuture()) {
                    $lembrete->forceFill(['ativo' => true, 'suspenso_lixeira' => false])->save();
                }
            });
        });

        return redirect()->route('lixeira.index', $this->consultaRetorno($request))
            ->with('sucesso', "Nota restaurada para {$destino}.");
    }

    public function destruir(Request $request, int $nota): RedirectResponse
    {
        $registro = $this->notaRemovida($nota);
        Gate::authorize('forceDelete', $registro);
        $registro->forceDelete();

        return redirect()->route('lixeira.index', $this->consultaRetorno($request))
            ->with('sucesso', 'Nota excluída definitivamente.');
    }

    private function notaRemovida(int $id): Nota
    {
        return Nota::onlyTrashed()->findOrFail($id);
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
}
