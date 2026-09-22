@php
$urlsFundos = collect(\App\Enums\FundoNota::cases())
    ->mapWithKeys(fn ($fundo) => [$fundo->value => asset($fundo->caminho())])
    ->all();
@endphp

<x-app-layout>
    <div x-data="notasApp({}, @js($urlsFundos), @js(['termo' => $termo ?? '', 'secao' => $secao, 'url' => route('lixeira.index')]))" x-init="iniciarBusca()">
        @if (session('sucesso'))
            <div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>
        @endif

        <div class="titulo-notas">
            <div><span class="etiqueta">EXCLUSÃO REVERSÍVEL</span><h1>Lixeira</h1><p>Consulte, restaure ou exclua definitivamente suas notas removidas.</p></div>
        </div>

        <div class="ferramentas-notas">
            <x-busca-notas :action="route('lixeira.index')" :termo="$termo" placeholder="Buscar na lixeira" />
        </div>

        <div class="estado-consulta">
            <span x-cloak x-show="buscaCarregando" role="status" aria-live="polite">Buscando…</span>
            <span x-cloak x-show="erroBusca" x-text="erroBusca" class="falha-busca" role="alert" aria-atomic="true"></span>
        </div>
        <div id="resultados-busca" x-ref="resultados" x-bind:aria-busy="buscaCarregando.toString()">
            @include('notas._resultados')
        </div>

        <div class="proximos-passos"><span class="indicador"></span><p>A busca consulta a lixeira. Não há eliminação automática.</p></div>

        <x-modal name="consultar-nota" max-width="lg" focusable titulo-id="titulo-modal-leitura">
            <div class="formulario-nota leitura-nota" x-bind:class="classeAparencia(leitura)" x-bind:style="estiloAparencia(leitura)">
                <div class="cabecalho-modal">
                    <div><span class="etiqueta">SOMENTE LEITURA</span><h2 id="titulo-modal-leitura">Nota removida</h2></div>
                    <button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'consultar-nota')" aria-label="Fechar consulta"><x-icone nome="fechar" /></button>
                </div>
                <div x-show="carregandoLeitura" class="carregando-nota" role="status">Abrindo nota…</div>
                <div x-show="erroLeitura" x-text="erroLeitura" class="erro-geral" role="alert"></div>
                <div x-show="!carregandoLeitura && !erroLeitura">
                    <div class="estado-leitura"><span x-show="leitura.fixada">Fixada</span><span x-show="leitura.arquivada">Antes em Arquivadas</span></div>
                    <section class="campo-leitura"><h3>Título</h3><p x-text="leitura.titulo || 'Sem título'"></p></section>
                    <section class="campo-leitura"><h3>Descrição</h3><p x-text="leitura.descricao || 'Sem descrição'"></p></section>
                    <p class="ajuda-formulario">Restaure a nota para voltar a editá-la.</p>
                    <div class="acoes-modal"><button type="button" class="botao-principal" x-on:click="$dispatch('close-modal', 'consultar-nota')">Fechar</button></div>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>