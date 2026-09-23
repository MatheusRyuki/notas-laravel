<x-app-layout>
    @if(session('sucesso'))<div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>@endif
    @if($errors->any())<div class="aviso-erro" role="alert">{{ $errors->first() }}</div>@endif
    <div class="titulo-notas"><div><span class="etiqueta">ACESSO ENTRE CONTAS</span><h1>Compartilhamentos</h1><p>Convide contas cadastradas e controle quem pode consultar ou editar.</p></div></div>

    <section class="painel-secao"><h2>Convites recebidos</h2>
        @forelse($convites as $convite)
            <article class="linha-compartilhamento"><div><strong>{{ $convite->nota->titulo ?: 'Nota sem título' }}</strong><p>{{ $convite->nota->usuario->name }} convidou você como {{ $convite->papel->rotulo() }}.</p></div>
                <form method="POST" action="{{ route('convites.responder', $convite->token) }}">@csrf @method('PATCH')<button name="resposta" value="aceitar">Aceitar</button><button name="resposta" value="recusar">Recusar</button></form>
            </article>
        @empty<p>Nenhum convite pendente.</p>@endforelse
    </section>

    <section class="painel-secao"><h2>Notas de sua propriedade</h2>
        @forelse($proprias as $nota)
            <article class="bloco-compartilhamento" @if(request('nota') == $nota->id) id="nota-selecionada" @endif>
                <h3>{{ $nota->titulo ?: 'Nota sem título' }}</h3>
                <form method="POST" action="{{ route('notas.convidar', $nota) }}">@csrf
                    <label>E-mail exato da conta <input type="email" name="email" required></label>
                    <label>Papel <select name="papel"><option value="editor">Editor</option><option value="leitor">Leitor</option></select></label>
                    <button type="submit">Convidar dentro do aplicativo</button>
                </form>
                <ul>
                    @forelse($nota->participantes as $participante)
                        <li><span>{{ $participante->name }} — {{ ucfirst($participante->pivot->papel) }}</span>
                            <form method="POST" action="{{ route('notas.participantes.papel', [$nota, $participante]) }}">@csrf @method('PATCH')<select name="papel"><option value="editor" @selected($participante->pivot->papel==='editor')>Editor</option><option value="leitor" @selected($participante->pivot->papel==='leitor')>Leitor</option></select><button type="submit">Alterar</button></form>
                            <form method="POST" action="{{ route('notas.participantes.revogar', [$nota, $participante]) }}">@csrf @method('DELETE')<button type="submit">Revogar acesso</button></form>
                        </li>
                    @empty<li>Nenhum participante.</li>@endforelse
                </ul>
            </article>
        @empty<p>Você ainda não possui notas.</p>@endforelse
    </section>

    <section class="painel-secao"><h2>Notas compartilhadas com você</h2>
        @forelse($compartilhadas as $nota)
            <article class="linha-compartilhamento"><div><strong>{{ $nota->titulo ?: 'Nota sem título' }}</strong><p>Proprietário: {{ $nota->usuario->name }} · Papel: {{ ucfirst($nota->pivot->papel) }}</p></div>
                <form method="POST" action="{{ route('notas.compartilhamento.sair', $nota) }}">@csrf @method('DELETE')<button type="submit">Sair da nota</button></form>
            </article>
        @empty<p>Nenhuma nota compartilhada aceita.</p>@endforelse
    </section>
</x-app-layout>
