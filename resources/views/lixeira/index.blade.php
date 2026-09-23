@php
$urlsFundos = collect(\App\Enums\FundoNota::cases())->mapWithKeys(fn ($fundo) => [$fundo->value => asset($fundo->caminho())])->all();
$urlBusca = route('lixeira.index', array_filter(['ordem' => $ordem, 'etiquetas' => $etiquetasSelecionadas]));
@endphp

<x-app-layout>
    <div x-data="notasApp({}, @js($urlsFundos), @js(['termo' => $termo ?? '', 'secao' => $secao, 'url' => $urlBusca]))" x-init="iniciarBusca()">
        <div x-cloak x-show="mensagemInterface" class="aviso-sucesso" role="status" x-text="mensagemInterface"></div>
        @if (session('sucesso'))<div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>@endif
        @if($errors->notas->isNotEmpty())<div class="aviso-erro" role="alert">{{ $errors->notas->first() }}</div>@endif

        <div class="titulo-notas"><div><span class="etiqueta">EXCLUSÃO REVERSÍVEL</span><h1>Lixeira</h1><p>Consulte, restaure ou exclua definitivamente suas notas removidas.</p></div></div>

        <div class="ferramentas-notas"><x-busca-notas :action="route('lixeira.index')" :termo="$termo" :ordem="$ordem" :etiquetas-selecionadas="$etiquetasSelecionadas" placeholder="Buscar na lixeira" /></div>

        <x-filtros-notas
            :action="route('lixeira.index')"
            :termo="$termo"
            :ordem="$ordem"
            :opcoes-ordem="[
                'atualizacao' => 'Remoção recente',
                'criacao' => 'Criação recente',
                'titulo' => 'Ordem alfabética',
            ]"
            :etiquetas="$etiquetas"
            :etiquetas-selecionadas="$etiquetasSelecionadas"
            secao="lixeira"
        />

        <div class="barra-selecao">
            <button type="button" x-on:click="modoSelecao=!modoSelecao; if(!modoSelecao) selecionadas=[]" x-text="modoSelecao ? 'Sair da seleção' : 'Selecionar notas'"></button>
            <form x-cloak x-show="modoSelecao" method="POST" action="{{ route('notas.lote') }}">@csrf
                <input type="hidden" name="secao" value="lixeira"><input type="hidden" name="acao" value="restaurar">
                @foreach(collect(request()->only(['q','ordem','etiquetas']))->reject(fn($v)=>$v===null||$v===''||$v===[]) as $nome=>$valor)@foreach((array)$valor as $item)<input type="hidden" name="{{ is_array($valor)?$nome.'[]':$nome }}" value="{{ $item }}">@endforeach @endforeach
                <template x-for="id in selecionadas" :key="id"><input type="hidden" name="notas[]" :value="id"></template>
                <strong x-text="selecionadas.length + ' selecionada(s)'"></strong><button type="submit">Restaurar selecionadas</button>
            </form>
            <noscript><p>A restauração em lote exige JavaScript; a restauração individual continua disponível.</p></noscript>
        </div>

        <div class="estado-consulta"><span x-cloak x-show="buscaCarregando" role="status" aria-live="polite">Buscando…</span><span x-cloak x-show="erroBusca" x-text="erroBusca" class="falha-busca" role="alert" aria-atomic="true"></span></div>
        <div id="resultados-busca" x-ref="resultados" x-bind:aria-busy="buscaCarregando.toString()">@include('notas._resultados')</div>

        <div class="proximos-passos"><span class="indicador"></span><p>Etiquetas podem ser consultadas na lixeira, mas só voltam a ser alteradas depois da restauração.</p></div>

        <x-modal name="consultar-nota" max-width="lg" focusable titulo-id="titulo-modal-leitura">
            <div class="formulario-nota leitura-nota" x-bind:class="classeAparencia(leitura)" x-bind:style="estiloAparencia(leitura)">
                <div class="cabecalho-modal"><div><span class="etiqueta">SOMENTE LEITURA</span><h2 id="titulo-modal-leitura">Nota removida</h2></div><button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'consultar-nota')" aria-label="Fechar consulta"><x-icone nome="fechar" /></button></div>
                <div x-show="carregandoLeitura" class="carregando-nota" role="status">Abrindo nota…</div><div x-show="erroLeitura" x-text="erroLeitura" class="erro-geral" role="alert"></div>
                <div x-show="!carregandoLeitura && !erroLeitura">
                    <div class="estado-leitura"><span x-show="leitura.fixada">Fixada</span><span x-show="leitura.arquivada">Antes em Arquivadas</span></div>
                    <section class="campo-leitura"><h3>Título</h3><p x-text="leitura.titulo || 'Sem título'"></p></section>
                    <section class="campo-leitura"><h3>Descrição</h3><p x-text="leitura.descricao || 'Sem descrição'"></p></section>
                    <ul class="lista-leitura" x-show="leitura.tipo_conteudo === 'lista'"><template x-for="item in leitura.itens"><li x-text="(item.concluido ? 'Concluído: ' : 'Pendente: ') + item.texto"></li></template></ul>
                    <p class="ajuda-formulario">Restaure a nota para voltar a editá-la.</p><div class="acoes-modal"><button type="button" class="botao-principal" x-on:click="$dispatch('close-modal','consultar-nota')">Fechar</button></div>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
