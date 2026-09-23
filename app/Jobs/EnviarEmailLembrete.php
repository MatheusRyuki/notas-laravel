<?php

namespace App\Jobs;

use App\Models\Lembrete;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class EnviarEmailLembrete implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $lembreteId,
        public string $agendadoEm,
    ) {}

    public function handle(): void
    {
        $lembrete = Lembrete::with(['nota', 'usuario'])->find($this->lembreteId);
        if (! $lembrete || ! $lembrete->processado_em || $lembrete->agendado_em->toISOString() !== $this->agendadoEm) {
            return;
        }
        if (! $lembrete->nota || $lembrete->nota->trashed() || ! $lembrete->nota->newQuery()->whereKey($lembrete->nota_id)->acessiveisPor($lembrete->usuario)->exists()) {
            return;
        }

        $titulo = $lembrete->nota->titulo ?: 'Nota sem título';
        Mail::raw("Lembrete da nota: {$titulo}", function ($mensagem) use ($lembrete, $titulo): void {
            $mensagem->to($lembrete->usuario->email)->subject("Lembrete: {$titulo}");
        });
    }
}
