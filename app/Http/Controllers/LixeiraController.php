<?php

namespace App\Http\Controllers;

use App\Enums\CorNota;
use App\Http\Requests\BuscarNotasRequest;
use App\Models\Nota;
use App\Support\BuscaNotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LixeiraController extends Controller
{
    public function index(BuscarNotasRequest $request): View|JsonResponse
    {
        $termo = $request->validated('q');
        $consulta = $request->user()->notas()->onlyTrashed();
        $notas = BuscaNotas::aplicar($consulta, $termo)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->get();

        $dados = ['notas' => $notas, 'secao' => 'lixeira', 'termo' => $termo];

        if ($request->expectsJson()) {
            return response()->json([
                'secao' => 'lixeira',
                'termo' => $termo ?? '',
                'total' => $notas->count(),
                'html' => view('notas._resultados', $dados)->render(),
            ]);
        }

        return view('lixeira.index', $dados);
    }

    public function show(Request $request, int $nota): JsonResponse
    {
        $notaRemovida = $this->notaRemovida($nota);
        Gate::authorize('view', $notaRemovida);

        return response()->json([
            'id' => $notaRemovida->id,
            'titulo' => $notaRemovida->titulo,
            'descricao' => $notaRemovida->descricao,
            'tipo_aparencia' => $notaRemovida->tipo_aparencia->value,
            'cor' => $notaRemovida->cor?->value ?? CorNota::Padrao->value,
            'fundo' => $notaRemovida->fundo()?->value,
            'fixada' => $notaRemovida->fixada,
            'arquivada' => $notaRemovida->arquivada,
        ]);
    }

    public function confirmarExclusao(Request $request, int $nota): View
    {
        $notaRemovida = $this->notaRemovida($nota);
        Gate::authorize('forceDelete', $notaRemovida);

        return view('lixeira.confirmar-exclusao', ['nota' => $notaRemovida, 'termo' => mb_substr(trim((string) $request->query('q', '')), 0, 100)]);
    }

    public function restaurar(Request $request, int $nota): RedirectResponse
    {
        $notaRemovida = $this->notaRemovida($nota);
        Gate::authorize('restore', $notaRemovida);
        $destino = $notaRemovida->arquivada ? 'Arquivadas' : 'Minhas notas';
        $notaRemovida->restore();

        return redirect()->route('lixeira.index', $this->consultaRetorno($request))
            ->with('sucesso', "Nota restaurada para {$destino}.");
    }

    public function destruir(Request $request, int $nota): RedirectResponse
    {
        $notaRemovida = $this->notaRemovida($nota);
        Gate::authorize('forceDelete', $notaRemovida);
        $notaRemovida->forceDelete();

        return redirect()->route('lixeira.index', $this->consultaRetorno($request))
            ->with('sucesso', 'Nota excluída definitivamente.');
    }

    private function notaRemovida(int $id): Nota
    {
        return Nota::onlyTrashed()->findOrFail($id);
    }

    /** @return array<string, string> */
    private function consultaRetorno(Request $request): array
    {
        $termo = trim((string) $request->input('q', ''));

        return $termo === '' ? [] : ['q' => $termo];
    }
}
