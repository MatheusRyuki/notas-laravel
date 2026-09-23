<x-app-layout>
    @if(session('sucesso'))<div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>@endif
    @if($errors->any())<div class="aviso-erro" role="alert">{{ $errors->first() }}</div>@endif

    <div class="titulo-notas">
        <div>
            <span class="etiqueta">ACESSO ENTRE CONTAS</span>
            <h1>Compartilhamentos</h1>
            <p>Convide contas cadastradas e controle quem pode consultar ou editar.</p>
        </div>
    </div>

    <section class="painel-secao" aria-labelledby="titulo-convites">
        <div class="cabecalho-painel-secao">
            <div><span class="etiqueta">ENTRADAS PENDENTES</span><h2 id="titulo-convites">Convites recebidos</h2></div>
            @if($convites->isNotEmpty())<span class="contador-secao">{{ $convites->count() }}</span>@endif
        </div>

        @forelse($convites as $convite)
            <article class="linha-compartilhamento">
                <div class="resumo-compartilhamento">
                    <span class="estado-convite">Convite pendente</span>
                    <h3>{{ $convite->nota->titulo ?: 'Nota sem título' }}</h3>
                    <p>{{ $convite->nota->usuario->name }} convidou você como <strong>{{ $convite->papel->rotulo() }}</strong>.</p>
                </div>
                <form method="POST" action="{{ route('convites.responder', $convite->token) }}" class="acoes-compartilhamento">
                    @csrf @method('PATCH')
                    <button class="botao-principal botao-compacto" name="resposta" value="aceitar">Aceitar</button>
                    <button class="botao-secundario botao-compacto" name="resposta" value="recusar">Recusar</button>
                </form>
            </article>
        @empty
            <div class="estado-secao-vazio"><x-icone nome="compartilhar" /><p>Nenhum convite pendente.</p></div>
        @endforelse
    </section>

    <section class="painel-secao" aria-labelledby="titulo-proprias">
        <div class="cabecalho-painel-secao">
            <div><span class="etiqueta">VOCÊ ADMINISTRA</span><h2 id="titulo-proprias">Notas de sua propriedade</h2></div>
            @if($proprias->isNotEmpty())<span class="contador-secao">{{ $proprias->count() }}</span>@endif
        </div>

        <div class="lista-blocos-compartilhamento">
            @forelse($proprias as $nota)
                <article class="bloco-compartilhamento" @if(request('nota') == $nota->id) id="nota-selecionada" @endif>
                    <div class="cabecalho-bloco-compartilhamento">
                        <div><span class="estado-papel">Proprietário</span><h3>{{ $nota->titulo ?: 'Nota sem título' }}</h3></div>
                        <span class="resumo-participantes">{{ $nota->participantes->count() }} {{ $nota->participantes->count() === 1 ? 'participante' : 'participantes' }}</span>
                    </div>

                    <form method="POST" action="{{ route('notas.convidar', $nota) }}" class="form-convite">
                        @csrf
                        <div class="campo-controle campo-email-convite">
                            <label for="email-convite-{{ $nota->id }}">E-mail exato da conta</label>
                            <input id="email-convite-{{ $nota->id }}" type="email" name="email" required autocomplete="email" placeholder="pessoa@exemplo.com">
                        </div>
                        <div class="campo-controle campo-papel-convite">
                            <label for="papel-convite-{{ $nota->id }}">Papel</label>
                            <select id="papel-convite-{{ $nota->id }}" name="papel" class="controle-select">
                                <option value="editor">Editor</option>
                                <option value="leitor">Leitor</option>
                            </select>
                        </div>
                        <button type="submit" class="botao-principal">Convidar</button>
                    </form>

                    <div class="participantes-nota">
                        <h4>Participantes</h4>
                        <ul class="lista-participantes">
                            @forelse($nota->participantes as $participante)
                                <li class="participante">
                                    <div class="identidade-participante">
                                        <strong>{{ $participante->name }}</strong>
                                        <span>{{ $participante->email }}</span>
                                    </div>
                                    <div class="acoes-participante">
                                        <form method="POST" action="{{ route('notas.participantes.papel', [$nota, $participante]) }}" class="form-papel-participante">
                                            @csrf @method('PATCH')
                                            <label class="sr-only" for="papel-{{ $nota->id }}-{{ $participante->id }}">Papel de {{ $participante->name }}</label>
                                            <select id="papel-{{ $nota->id }}-{{ $participante->id }}" name="papel" class="controle-select">
                                                <option value="editor" @selected($participante->pivot->papel==='editor')>Editor</option>
                                                <option value="leitor" @selected($participante->pivot->papel==='leitor')>Leitor</option>
                                            </select>
                                            <button type="submit" class="botao-secundario botao-compacto">Alterar</button>
                                        </form>
                                        <form method="POST" action="{{ route('notas.participantes.revogar', [$nota, $participante]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="botao-destrutivo botao-compacto">Revogar acesso</button>
                                        </form>
                                    </div>
                                </li>
                            @empty
                                <li class="estado-participantes-vazio">Nenhum participante. Use o formulário acima para convidar alguém.</li>
                            @endforelse
                        </ul>
                    </div>
                </article>
            @empty
                <div class="estado-secao-vazio"><x-icone nome="notas" /><p>Você ainda não possui notas para compartilhar.</p></div>
            @endforelse
        </div>
    </section>

    <section class="painel-secao" aria-labelledby="titulo-compartilhadas">
        <div class="cabecalho-painel-secao">
            <div><span class="etiqueta">ACESSO RECEBIDO</span><h2 id="titulo-compartilhadas">Notas compartilhadas com você</h2></div>
            @if($compartilhadas->isNotEmpty())<span class="contador-secao">{{ $compartilhadas->count() }}</span>@endif
        </div>

        @forelse($compartilhadas as $nota)
            <article class="linha-compartilhamento">
                <div class="resumo-compartilhamento">
                    <span class="estado-papel">{{ ucfirst($nota->pivot->papel) }}</span>
                    <h3>{{ $nota->titulo ?: 'Nota sem título' }}</h3>
                    <p>Proprietário: {{ $nota->usuario->name }}</p>
                </div>
                <form method="POST" action="{{ route('notas.compartilhamento.sair', $nota) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="botao-destrutivo botao-compacto">Sair da nota</button>
                </form>
            </article>
        @empty
            <div class="estado-secao-vazio"><x-icone nome="compartilhar" /><p>Nenhuma nota compartilhada aceita.</p></div>
        @endforelse
    </section>
</x-app-layout>
