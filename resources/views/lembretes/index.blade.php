<x-app-layout>
    @if(session('sucesso'))<div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>@endif
    <div class="titulo-notas"><div><span class="etiqueta">AVISOS PESSOAIS</span><h1>Lembretes</h1><p>Horários são armazenados em UTC e apresentados no fuso escolhido.</p></div></div>

    <section class="painel-secao"><h2>Agendamentos</h2>
        @forelse($lembretes as $lembrete)
            <article class="linha-compartilhamento"><div><strong>{{ $lembrete->nota?->titulo ?: 'Nota sem título' }}</strong>
                <p>{{ $lembrete->agendado_em->setTimezone($lembrete->fuso_horario)->format('d/m/Y H:i') }} · {{ $lembrete->fuso_horario }}</p>
                <span>{{ $lembrete->suspenso_lixeira ? 'Suspenso enquanto a nota está na lixeira' : ($lembrete->ativo ? 'Agendado' : 'Processado ou aguardando reagendamento') }}</span></div>
                <form method="POST" action="{{ route('lembretes.destroy', $lembrete) }}">@csrf @method('DELETE')<button type="submit">Cancelar</button></form>
            </article>
        @empty<p>Nenhum lembrete agendado.</p>@endforelse
    </section>

    <section class="painel-secao"><h2>Notificações internas</h2>
        @forelse($notificacoes as $notificacao)
            <article class="linha-compartilhamento {{ $notificacao->lida_em ? '' : 'nao-lida' }}"><div><strong>{{ $notificacao->dados['titulo'] ?? 'Lembrete' }}</strong><p>{{ $notificacao->created_at->format('d/m/Y H:i') }}</p></div>
                @unless($notificacao->lida_em)<form method="POST" action="{{ route('notificacoes.ler', $notificacao) }}">@csrf @method('PATCH')<button type="submit">Marcar como lida</button></form>@endunless
            </article>
        @empty<p>Nenhuma notificação.</p>@endforelse
    </section>
</x-app-layout>
