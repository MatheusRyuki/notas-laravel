<?php

namespace App\Services;

use App\Models\Nota;
use App\Models\OperacaoDesfazer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesfazerService
{
    /** @param list<array<string, mixed>> $itens */
    public function registrar(User $usuario, string $tipo, array $itens, array $retorno = []): OperacaoDesfazer
    {
        $operacao = new OperacaoDesfazer([
            'tipo' => $tipo,
            'dados' => ['itens' => $itens, 'retorno' => $retorno],
            'expira_em' => now()->addMinutes(5),
        ]);
        $usuario->operacoesDesfazer()->save($operacao);

        return $operacao;
    }

    public function executar(User $usuario, string $token): array
    {
        return DB::transaction(function () use ($usuario, $token): array {
            $operacao = OperacaoDesfazer::query()
                ->where('token', $token)
                ->where('usuario_id', $usuario->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operacao->utilizada_em !== null) {
                throw ValidationException::withMessages(['desfazer' => 'Esta ação já foi desfeita.']);
            }

            if ($operacao->expira_em->isPast()) {
                throw ValidationException::withMessages(['desfazer' => 'O prazo de cinco minutos para desfazer terminou.']);
            }

            $notas = [];
            foreach ($operacao->dados['itens'] as $item) {
                $nota = Nota::withTrashed()->whereKey($item['nota_id'])->lockForUpdate()->first();

                if (! $nota || ! $nota->pertenceA($usuario) || $nota->revisao !== $item['revisao_esperada']) {
                    throw ValidationException::withMessages([
                        'desfazer' => 'Não foi possível desfazer: a nota mudou ou a permissão não existe mais.',
                    ]);
                }

                $notas[] = [$nota, $item];
            }

            foreach ($notas as [$nota, $item]) {
                if ($operacao->tipo === 'arquivamento') {
                    if ($nota->trashed()) {
                        throw ValidationException::withMessages(['desfazer' => 'Não foi possível desfazer porque a nota está na lixeira.']);
                    }
                    $nota->arquivada = (bool) $item['arquivada_anterior'];
                    $nota->avancarRevisao();
                    $nota->save();
                } elseif ($operacao->tipo === 'lixeira') {
                    if (! $nota->trashed()) {
                        throw ValidationException::withMessages(['desfazer' => 'Não foi possível desfazer porque a nota já foi restaurada.']);
                    }
                    $nota->revisao++;
                    $nota->restore();
                    $nota->lembretes()->each(function ($lembrete): void {
                        if ($lembrete->agendado_em->isFuture()) {
                            $lembrete->forceFill(['ativo' => true, 'suspenso_lixeira' => false])->save();
                        }
                    });
                } else {
                    throw ValidationException::withMessages(['desfazer' => 'Esta operação não pode ser desfeita.']);
                }
            }

            $operacao->utilizada_em = now();
            $operacao->save();

            return $operacao->dados['retorno'] ?? [];
        }, 3);
    }
}
