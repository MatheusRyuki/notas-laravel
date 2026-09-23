@if ($notas->isEmpty())
    <section class="estado-vazio {{ $termo ? 'estado-sem-resultados' : '' }}" aria-labelledby="titulo-vazio">
        <div class="icone-vazio" aria-hidden="true">
            <x-icone :nome="$termo ? 'busca' : ($secao === 'lixeira' ? 'lixeira' : 'mais')" />
        </div>
        @if ($termo)
            <span class="etiqueta">NENHUM RESULTADO</span>
            <h2 id="titulo-vazio">Nenhuma nota encontrada.</h2>
            <p>Não encontramos título ou descrição com “{{ $termo }}” nesta seção.</p>
            <button class="atalho-criacao" type="button" x-on:click="limparBusca">Limpar busca <x-icone nome="seta-direita" /></button>
        @elseif ($secao === 'lixeira')
            <span class="etiqueta">NENHUMA NOTA REMOVIDA</span>
            <h2 id="titulo-vazio">A lixeira está vazia.</h2>
            <p>Notas movidas para a lixeira poderão ser restauradas antes da exclusão definitiva.</p>
            <a class="atalho-criacao" href="{{ route('notas.inicio') }}">Voltar às minhas notas <x-icone nome="seta-direita" /></a>
        @elseif ($secao === 'arquivadas')
            <span class="etiqueta">NENHUMA NOTA GUARDADA</span>
            <h2 id="titulo-vazio">Nenhuma nota arquivada.</h2>
            <p>Quando você arquivar uma nota, ela aparecerá aqui.</p>
            <a class="atalho-criacao" href="{{ route('notas.inicio') }}">Voltar às minhas notas <x-icone nome="seta-direita" /></a>
        @else
            <span class="etiqueta">TUDO COMEÇA COM UMA IDEIA</span>
            <h2 id="titulo-vazio">Crie sua primeira nota.</h2>
            <p>Registre uma ideia usando um título, uma descrição ou os dois.</p>
            <button class="atalho-criacao" type="button" x-on:click="$dispatch('open-modal', 'criar-nota')">Criar uma nota <x-icone nome="seta-direita" /></button>
        @endif
    </section>
@else
    <div class="resumo-listagem">
        <span>{{ $notas->count() }} {{ $notas->count() === 1 ? 'nota' : 'notas' }}{{ $termo ? ' encontrada'.($notas->count() === 1 ? '' : 's') : '' }}</span>
        <span>{{ match($ordem ?? 'atualizacao') { 'criacao' => 'Criadas recentemente primeiro', 'titulo' => 'Título em ordem alfabética', default => ($secao === 'lixeira' ? 'Removidas recentemente primeiro' : 'Atualizadas recentemente primeiro') } }}</span>
    </div>

    @if ($secao === 'lixeira')
        <section aria-label="Notas na lixeira"><div class="grade-notas">
            @foreach ($notas as $nota)<x-cartao-nota :nota="$nota" :etiquetas="$etiquetas" em-lixeira />@endforeach
        </div></section>
    @elseif ($secao === 'arquivadas')
        <section aria-label="Notas arquivadas"><div class="grade-notas">
            @foreach ($notas as $nota)<x-cartao-nota :nota="$nota" :etiquetas="$etiquetas" em-arquivadas />@endforeach
        </div></section>
    @elseif ($notasFixadas->isNotEmpty())
        <section class="grupo-notas" aria-labelledby="titulo-fixadas">
            <h2 id="titulo-fixadas">Fixadas <span>{{ $notasFixadas->count() }}</span></h2>
            <div class="grade-notas">@foreach ($notasFixadas as $nota)<x-cartao-nota :nota="$nota" :etiquetas="$etiquetas" />@endforeach</div>
        </section>
        @if ($outrasNotas->isNotEmpty())
            <section class="grupo-notas" aria-labelledby="titulo-outras">
                <h2 id="titulo-outras">Outras <span>{{ $outrasNotas->count() }}</span></h2>
                <div class="grade-notas">@foreach ($outrasNotas as $nota)<x-cartao-nota :nota="$nota" :etiquetas="$etiquetas" />@endforeach</div>
            </section>
        @endif
    @else
        <section aria-label="Suas notas"><div class="grade-notas">
            @foreach ($outrasNotas as $nota)<x-cartao-nota :nota="$nota" :etiquetas="$etiquetas" />@endforeach
        </div></section>
    @endif
@endif