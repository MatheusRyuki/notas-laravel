<x-app-layout>
    @if(session('sucesso'))
        <div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>
    @endif

    <div class="titulo-notas">
        <div>
            <span class="etiqueta">AVISOS PESSOAIS</span>
            <h1>Lembretes</h1>
            <p>Veja seus agendamentos no fuso escolhido e acompanhe notificações recentes.</p>
        </div>
    </div>

    <div class="grade-lembretes">
        <section class="painel-secao painel-lembretes" aria-labelledby="titulo-agendamentos">
            <div class="cabecalho-painel-secao">
                <div>
                    <span class="etiqueta">PROGRAMADOS</span>
                    <h2 id="titulo-agendamentos">Agendamentos</h2>
                </div>
                <span class="contador-secao" aria-label="{{ $lembretes->count() }} {{ $lembretes->count() === 1 ? 'lembrete' : 'lembretes' }}">{{ $lembretes->count() }}</span>
            </div>

            <div class="lista-lembretes">
                @forelse($lembretes as $lembrete)
                    <article class="linha-lembrete">
                        <span class="icone-linha-lembrete" aria-hidden="true"><x-icone nome="relogio" /></span>
                        <div class="conteudo-linha-lembrete">
                            <strong>{{ $lembrete->nota?->titulo ?: 'Nota sem título' }}</strong>
                            <time datetime="{{ $lembrete->agendado_em->toIso8601String() }}">
                                {{ $lembrete->agendado_em->setTimezone($lembrete->fuso_horario)->format('d/m/Y H:i') }}
                            </time>
                            <small>{{ $lembrete->fuso_horario }}</small>
                            <span class="estado-lembrete">
                                {{ $lembrete->suspenso_lixeira ? 'Suspenso enquanto a nota está na lixeira' : ($lembrete->ativo ? 'Agendado' : 'Processado ou aguardando reagendamento') }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('lembretes.destroy', $lembrete) }}">
                            @csrf
                            @method('DELETE')
                            <button class="botao-secundario botao-compacto" type="submit">Cancelar</button>
                        </form>
                    </article>
                @empty
                    <div class="estado-secao-vazio estado-lembretes-vazio">
                        <x-icone nome="relogio" />
                        <div>
                            <strong>Nenhum lembrete agendado</strong>
                            <span>Use “Mais ações” em uma nota para escolher uma data e horário.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="painel-secao painel-lembretes" aria-labelledby="titulo-notificacoes">
            <div class="cabecalho-painel-secao">
                <div>
                    <span class="etiqueta">ATUALIZAÇÕES</span>
                    <h2 id="titulo-notificacoes">Notificações internas</h2>
                </div>
                <span class="contador-secao" aria-label="{{ $notificacoes->count() }} {{ $notificacoes->count() === 1 ? 'notificação' : 'notificações' }}">{{ $notificacoes->count() }}</span>
            </div>

            <div class="lista-lembretes">
                @forelse($notificacoes as $notificacao)
                    <article class="linha-lembrete {{ $notificacao->lida_em ? '' : 'nao-lida' }}">
                        <span class="icone-linha-lembrete" aria-hidden="true"><x-icone nome="lembrete" /></span>
                        <div class="conteudo-linha-lembrete">
                            <strong>{{ $notificacao->dados['titulo'] ?? 'Lembrete' }}</strong>
                            <time datetime="{{ $notificacao->created_at->toIso8601String() }}">{{ $notificacao->created_at->format('d/m/Y H:i') }}</time>
                            <span class="estado-lembrete">{{ $notificacao->lida_em ? 'Lida' : 'Não lida' }}</span>
                        </div>
                        @unless($notificacao->lida_em)
                            <form method="POST" action="{{ route('notificacoes.ler', $notificacao) }}">
                                @csrf
                                @method('PATCH')
                                <button class="botao-secundario botao-compacto" type="submit"><x-icone nome="check" /> Marcar como lida</button>
                            </form>
                        @endunless
                    </article>
                @empty
                    <div class="estado-secao-vazio estado-lembretes-vazio">
                        <x-icone nome="lembrete" />
                        <div>
                            <strong>Nenhuma notificação</strong>
                            <span>Os avisos gerados pelos seus lembretes aparecerão aqui.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
