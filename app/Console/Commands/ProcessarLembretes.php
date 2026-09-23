<?php

namespace App\Console\Commands;

use App\Jobs\EnviarEmailLembrete;
use App\Models\Lembrete;
use App\Models\NotificacaoInterna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessarLembretes extends Command
{
    protected $signature = 'lembretes:processar';

    protected $description = 'Processa lembretes vencidos e ainda válidos';

    public function handle(): int
    {
        $ids = Lembrete::query()
            ->where('ativo', true)
            ->where('suspenso_lixeira', false)
            ->whereNull('processado_em')
            ->where('agendado_em', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $lembrete = Lembrete::with(['nota', 'usuario'])->whereKey($id)->lockForUpdate()->first();
                if (! $lembrete || ! $lembrete->ativo || $lembrete->processado_em || $lembrete->suspenso_lixeira) {
                    return;
                }

                $notaAcessivel = $lembrete->nota
                    && ! $lembrete->nota->trashed()
                    && $lembrete->nota->newQuery()->whereKey($lembrete->nota_id)->acessiveisPor($lembrete->usuario)->exists();

                if (! $notaAcessivel) {
                    $lembrete->forceFill(['ativo' => false])->save();

                    return;
                }

                $chave = 'lembrete:'.$lembrete->id.':'.$lembrete->agendado_em->timestamp;
                NotificacaoInterna::firstOrCreate(
                    ['chave' => $chave],
                    [
                        'usuario_id' => $lembrete->usuario_id,
                        'nota_id' => $lembrete->nota_id,
                        'tipo' => 'lembrete',
                        'dados' => [
                            'titulo' => $lembrete->nota->titulo ?: 'Nota sem título',
                            'agendado_em' => $lembrete->agendado_em->toISOString(),
                        ],
                    ],
                );

                $lembrete->forceFill(['ativo' => false, 'processado_em' => now()])->save();
                if ($lembrete->enviar_email) {
                    EnviarEmailLembrete::dispatch($lembrete->id, $lembrete->agendado_em->toISOString())->afterCommit();
                }
            }, 3);
        }

        $this->info($ids->count().' lembrete(s) verificado(s).');

        return self::SUCCESS;
    }
}
