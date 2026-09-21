<?php

namespace App\Http\Controllers;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Http\Requests\ArquivarNotaRequest;
use App\Http\Requests\BuscarNotasRequest;
use App\Http\Requests\FixarNotaRequest;
use App\Http\Requests\StoreNotaRequest;
use App\Http\Requests\UpdateNotaRequest;
use App\Models\Nota;
use App\Support\BuscaNotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $nota = new Nota;
        $nota->fill($request->safe()->only(['titulo', 'descricao']));
        $this->aplicarAparencia($nota, $request->validated());
        $request->user()->notas()->save($nota);

        return redirect()->route('notas.inicio', $this->consultaRetorno($request))->with('sucesso', 'Nota criada com sucesso.');
    }

    public function show(Nota $nota): JsonResponse
    {
        Gate::authorize('view', $nota);

        return response()->json([
            'id' => $nota->id,
            'titulo' => $nota->titulo,
            'descricao' => $nota->descricao,
            'tipo_aparencia' => $nota->tipo_aparencia->value,
            'cor' => $nota->cor?->value ?? CorNota::Padrao->value,
            'fundo' => $nota->fundo()?->value,
            'fixada' => $nota->fixada,
            'arquivada' => $nota->arquivada,
            'url_atualizacao' => route('notas.update', $nota),
        ]);
    }

    public function update(UpdateNotaRequest $request, Nota $nota): JsonResponse
    {
        $dados = $request->validated();
        $nota->fill($request->safe()->only(['titulo', 'descricao']));

        if (array_key_exists('tipo_aparencia', $dados)) {
            $this->aplicarAparencia($nota, $dados);
        }

        $nota->save();
        $request->session()->flash('sucesso', 'Nota atualizada com sucesso.');

        return response()->json([
            'mensagem' => 'Nota atualizada com sucesso.',
            'nota' => [
                'id' => $nota->id,
                'titulo' => $nota->titulo,
                'descricao' => $nota->descricao,
                'tipo_aparencia' => $nota->tipo_aparencia->value,
                'cor' => $nota->cor?->value ?? CorNota::Padrao->value,
                'fundo' => $nota->fundo()?->value,
                'arquivada' => $nota->arquivada,
            ],
        ]);
    }

    public function fixacao(FixarNotaRequest $request, Nota $nota): RedirectResponse
    {
        $nota->forceFill(['fixada' => $request->boolean('fixada')])->save();
        $mensagem = $nota->fixada ? 'Nota fixada com sucesso.' : 'Nota desafixada com sucesso.';

        return redirect()->route('notas.inicio', $this->consultaRetorno($request))->with('sucesso', $mensagem);
    }

    public function arquivamento(ArquivarNotaRequest $request, Nota $nota): RedirectResponse
    {
        $nota->forceFill(['arquivada' => $request->boolean('arquivada')])->save();

        if ($nota->arquivada) {
            return redirect()->route('notas.inicio', $this->consultaRetorno($request))->with('sucesso', 'Nota arquivada com sucesso.');
        }

        return redirect()->route('notas.arquivadas', $this->consultaRetorno($request))->with('sucesso', 'Nota desarquivada com sucesso.');
    }

    public function moverLixeira(Request $request, Nota $nota): RedirectResponse
    {
        Gate::authorize('delete', $nota);
        $rotaRetorno = $nota->arquivada ? 'notas.arquivadas' : 'notas.inicio';
        $nota->delete();

        return redirect()->route($rotaRetorno, $this->consultaRetorno($request))
            ->with('sucesso', 'Nota movida para a lixeira.');
    }

    private function listagem(BuscarNotasRequest $request, bool $arquivadas): View|JsonResponse
    {
        $termo = $request->validated('q');
        $secao = $arquivadas ? 'arquivadas' : 'ativas';
        $consulta = $request->user()->notas()->where('arquivada', $arquivadas);
        $notas = BuscaNotas::aplicar($consulta, $termo)
            ->orderByDesc('fixada')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $dados = [
            'notas' => $notas,
            'notasFixadas' => $notas->where('fixada', true),
            'outrasNotas' => $notas->where('fixada', false),
            'secaoArquivadas' => $arquivadas,
            'secao' => $secao,
            'termo' => $termo,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'secao' => $secao,
                'termo' => $termo ?? '',
                'total' => $notas->count(),
                'html' => view('notas._resultados', $dados)->render(),
            ]);
        }

        return view('notas.inicio', $dados);
    }

    /** @return array<string, string> */
    private function consultaRetorno(Request $request): array
    {
        $termo = trim((string) $request->input('q', ''));

        return $termo === '' ? [] : ['q' => $termo];
    }

    /** @param array<string, mixed> $dados */
    private function aplicarAparencia(Nota $nota, array $dados): void
    {
        $tipo = TipoAparencia::from($dados['tipo_aparencia']);

        if ($tipo === TipoAparencia::Imagem) {
            $fundo = FundoNota::from($dados['fundo']);
            $nota->forceFill([
                'tipo_aparencia' => TipoAparencia::Imagem,
                'cor' => null,
                'caminho_imagem' => $fundo->caminho(),
            ]);

            return;
        }

        $cor = CorNota::from($dados['cor']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Cor,
            'cor' => $cor === CorNota::Padrao ? null : $cor,
            'caminho_imagem' => null,
        ]);
    }
}
