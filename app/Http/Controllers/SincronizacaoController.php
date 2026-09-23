<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use App\Models\OperacaoSincronizacao;
use App\Services\NotaPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SincronizacaoController extends Controller
{
    public function bootstrap(Request $request, NotaPayloadService $payloads): JsonResponse
    {
        $notas = Nota::query()
            ->acessiveisPor($request->user())
            ->with(['itens', 'usuario'])
            ->orderBy('id')
            ->get()
            ->map(fn (Nota $nota) => $payloads->representar($nota, $request->user()))
            ->values();

        return response()->json([
            'conta_id' => $request->user()->id,
            'conta_nome' => $request->user()->name,
            'gerado_em' => now()->toISOString(),
            'notas' => $notas,
        ]);
    }

    public function sincronizar(Request $request, NotaPayloadService $payloads): JsonResponse
    {
        $dados = $request->validate([
            'conta_id' => ['required', 'integer'],
            'operacoes' => ['required', 'array', 'max:100'],
            'operacoes.*.uuid' => ['required', 'uuid'],
            'operacoes.*.acao' => ['required', 'in:criar,atualizar'],
            'operacoes.*.id_local' => ['nullable', 'uuid'],
            'operacoes.*.nota_uuid' => ['nullable', 'uuid'],
            'operacoes.*.revisao_base' => ['nullable', 'integer', 'min:1'],
            'operacoes.*.payload' => ['required', 'array'],
        ]);

        abort_unless((int) $dados['conta_id'] === $request->user()->id, 403);
        $resultados = [];

        foreach ($dados['operacoes'] as $operacao) {
            $anterior = OperacaoSincronizacao::where('usuario_id', $request->user()->id)
                ->where('operacao_uuid', $operacao['uuid'])
                ->first();
            if ($anterior) {
                $resultados[] = $anterior->resultado;

                continue;
            }

            $resultado = DB::transaction(function () use ($request, $operacao, $payloads): array {
                $existente = OperacaoSincronizacao::where('usuario_id', $request->user()->id)
                    ->where('operacao_uuid', $operacao['uuid'])
                    ->lockForUpdate()
                    ->first();
                if ($existente) {
                    return $existente->resultado;
                }

                try {
                    $resultado = $operacao['acao'] === 'criar'
                        ? $this->criar($request, $operacao, $payloads)
                        : $this->atualizar($request, $operacao, $payloads);
                } catch (ValidationException $exception) {
                    $resultado = [
                        'operacao_uuid' => $operacao['uuid'],
                        'status' => 'erro_validacao',
                        'erros' => $exception->errors(),
                    ];
                }

                $registro = new OperacaoSincronizacao([
                    'operacao_uuid' => $operacao['uuid'],
                    'acao' => $operacao['acao'],
                    'id_local' => $operacao['id_local'] ?? null,
                    'resultado' => $resultado,
                ]);
                $request->user()->operacoesSincronizacao()->save($registro);

                return $resultado;
            }, 3);

            $resultados[] = $resultado;
        }

        return response()->json(['conta_id' => $request->user()->id, 'resultados' => $resultados]);
    }

    private function criar(Request $request, array $operacao, NotaPayloadService $payloads): array
    {
        $idLocal = $operacao['id_local'] ?? null;
        if (! $idLocal) {
            throw ValidationException::withMessages(['id_local' => 'A nota offline precisa de uma identificação local.']);
        }

        $jaCriada = Nota::withTrashed()->where('uuid_sincronizacao', $idLocal)->first();
        if ($jaCriada) {
            abort_unless($jaCriada->usuario_id === $request->user()->id, 403);

            return [
                'operacao_uuid' => $operacao['uuid'],
                'status' => 'sincronizado',
                'id_local' => $idLocal,
                'nota' => $payloads->representar($jaCriada, $request->user()),
            ];
        }

        $dados = $payloads->validar($operacao['payload']);
        $nota = new Nota(['uuid_sincronizacao' => $idLocal, 'tipo_conteudo' => $dados['tipo_conteudo']]);
        $request->user()->notas()->save($nota);
        $payloads->aplicar($nota, $dados);

        return [
            'operacao_uuid' => $operacao['uuid'],
            'status' => 'sincronizado',
            'id_local' => $idLocal,
            'nota' => $payloads->representar($nota->fresh(), $request->user()),
        ];
    }

    private function atualizar(Request $request, array $operacao, NotaPayloadService $payloads): array
    {
        $nota = Nota::where('uuid_sincronizacao', $operacao['nota_uuid'] ?? '')->lockForUpdate()->first();
        if (! $nota || ! $request->user()->can('view', $nota)) {
            return [
                'operacao_uuid' => $operacao['uuid'],
                'status' => 'revogado_ou_excluido',
                'nota_uuid' => $operacao['nota_uuid'] ?? null,
                'mensagem' => 'A nota foi excluída ou seu acesso foi revogado.',
            ];
        }

        if (! $request->user()->can('update', $nota)) {
            return [
                'operacao_uuid' => $operacao['uuid'],
                'status' => 'somente_leitura',
                'nota_uuid' => $nota->uuid_sincronizacao,
                'nota' => $payloads->representar($nota, $request->user()),
                'mensagem' => 'Sua permissão mudou para somente leitura. A alteração local não foi aplicada.',
            ];
        }

        if ($nota->revisao !== (int) ($operacao['revisao_base'] ?? 0)) {
            return [
                'operacao_uuid' => $operacao['uuid'],
                'status' => 'conflito',
                'nota_uuid' => $nota->uuid_sincronizacao,
                'local' => $operacao['payload'],
                'atual' => $payloads->representar($nota, $request->user()),
            ];
        }

        $dados = $payloads->validar($operacao['payload'], $nota);
        $payloads->aplicar($nota, $dados);
        $nota->avancarRevisao();
        $nota->save();

        return [
            'operacao_uuid' => $operacao['uuid'],
            'status' => 'sincronizado',
            'nota' => $payloads->representar($nota->fresh(), $request->user()),
        ];
    }
}
