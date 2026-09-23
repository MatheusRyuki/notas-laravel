@php
$criacao = [
    'tipo_aparencia' => old('tipo_aparencia', \App\Enums\TipoAparencia::Cor->value),
    'cor' => old('cor', \App\Enums\CorNota::Padrao->value),
    'fundo' => old('fundo', \App\Enums\FundoNota::cases()[0]->value),
    'tipo_conteudo' => old('tipo_conteudo', \App\Enums\TipoNota::Texto->value),
    'itens' => old('itens', [['texto' => '', 'concluido' => false]]),
];
$urlsFundos = collect(\App\Enums\FundoNota::cases())->mapWithKeys(fn ($fundo) => [$fundo->value => asset($fundo->caminho())])->all();
$rotaSecao = $secaoArquivadas ? 'notas.arquivadas' : 'notas.inicio';
$urlBusca = route($rotaSecao, array_filter(['ordem' => $ordem, 'etiquetas' => $etiquetasSelecionadas]));
@endphp

<x-app-layout>
    <div x-data="notasApp(@js($criacao), @js($urlsFundos), @js(['termo' => $termo ?? '', 'secao' => $secao, 'url' => $urlBusca]))" x-init="iniciarBusca()">
        <div x-cloak x-show="mensagemInterface" class="aviso-sucesso" role="status" x-text="mensagemInterface"></div>
        @if (session('sucesso'))
            <div class="aviso-sucesso" role="status">
                {{ session('sucesso') }}
                @if(session('desfazer'))
                    <form method="POST" action="{{ route('desfazer', session('desfazer')) }}" class="form-desfazer">@csrf<button type="submit">Desfazer</button><span>Disponível por cinco minutos.</span></form>
                @endif
            </div>
        @endif
        @foreach(['desfazer', 'notas', 'etiquetas'] as $bolsa)
            @if($errors->{$bolsa}->isNotEmpty())<div class="aviso-erro" role="alert">{{ $errors->{$bolsa}->first() }}</div>@endif
        @endforeach

        <div class="titulo-notas">
            <div>
                <span class="etiqueta">{{ $secaoArquivadas ? 'NOTAS FORA DA TELA PRINCIPAL' : 'SEU ESPAÇO PESSOAL' }}</span>
                <h1>{{ $secaoArquivadas ? 'Arquivadas' : 'Minhas notas' }}</h1>
                <p>{{ $secaoArquivadas ? 'Notas guardadas continuam disponíveis para consulta e edição.' : 'Organize textos, listas e notas compartilhadas.' }}</p>
            </div>
        </div>

        <div class="ferramentas-notas">
            <x-busca-notas :action="route($rotaSecao)" :termo="$termo" :ordem="$ordem" :etiquetas-selecionadas="$etiquetasSelecionadas" :placeholder="$secaoArquivadas ? 'Buscar nas arquivadas' : 'Buscar nas suas notas'" />
            @unless ($secaoArquivadas)<button class="nova-nota" type="button" x-on:click="$dispatch('open-modal', 'criar-nota')"><x-icone nome="mais" /> Criar nota</button>@endunless
        </div>

        <div class="painel-filtros">
            <form method="GET" action="{{ route($rotaSecao) }}">
                @if($termo)<input type="hidden" name="q" value="{{ $termo }}">@endif
                <label>Ordenar
                    <select name="ordem">
                        <option value="atualizacao" @selected($ordem === 'atualizacao')>Atualização recente</option>
                        <option value="criacao" @selected($ordem === 'criacao')>Criação recente</option>
                        <option value="titulo" @selected($ordem === 'titulo')>Ordem alfabética</option>
                    </select>
                </label>
                <fieldset><legend>Filtrar por qualquer etiqueta</legend>
                    @foreach($etiquetas as $etiqueta)<label><input type="checkbox" name="etiquetas[]" value="{{ $etiqueta->id }}" @checked(in_array($etiqueta->id, $etiquetasSelecionadas))> {{ $etiqueta->nome }}</label>@endforeach
                </fieldset>
                <button type="submit">Aplicar filtros</button>
            </form>
            <details>
                <summary>Gerenciar etiquetas</summary>
                <form method="POST" action="{{ route('etiquetas.store') }}">@csrf<label>Nova etiqueta <input name="nome" maxlength="60" required></label><button type="submit">Criar</button></form>
                @foreach($etiquetas as $etiqueta)
                    <div class="linha-etiqueta">
                        <form method="POST" action="{{ route('etiquetas.update', $etiqueta) }}">@csrf @method('PATCH')<input name="nome" value="{{ $etiqueta->nome }}" maxlength="60" required><button type="submit">Renomear</button></form>
                        <form method="POST" action="{{ route('etiquetas.destroy', $etiqueta) }}">@csrf @method('DELETE')<button type="submit">Excluir</button></form>
                    </div>
                @endforeach
            </details>
        </div>

        <div class="barra-selecao">
            <button type="button" x-on:click="modoSelecao = !modoSelecao; if (!modoSelecao) selecionadas=[]" x-text="modoSelecao ? 'Sair da seleção' : 'Selecionar notas'"></button>
            <form x-cloak x-show="modoSelecao" method="POST" action="{{ route('notas.lote') }}" x-on:submit="if (selecionadas.length === 0) { $event.preventDefault(); mensagemInterface='Selecione pelo menos uma nota.' }">
                @csrf
                <input type="hidden" name="secao" value="{{ $secao }}">
                @foreach(collect(request()->only(['q','ordem','etiquetas']))->reject(fn($v) => $v === null || $v === '' || $v === []) as $nome => $valor)
                    @foreach((array)$valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach
                @endforeach
                <template x-for="id in selecionadas" :key="id"><input type="hidden" name="notas[]" :value="id"></template>
                <strong x-text="selecionadas.length + ' selecionada(s)'"></strong>
                <select name="acao" required>
                    @if($secaoArquivadas)<option value="desarquivar">Desarquivar</option>@else<option value="arquivar">Arquivar</option>@endif
                    <option value="lixeira">Mover à lixeira</option>
                    <option value="aplicar_etiqueta">Aplicar etiqueta</option>
                    <option value="remover_etiqueta">Remover etiqueta</option>
                </select>
                <select name="etiqueta_id"><option value="">Escolha a etiqueta quando necessário</option>@foreach($etiquetas as $etiqueta)<option value="{{ $etiqueta->id }}">{{ $etiqueta->nome }}</option>@endforeach</select>
                <button type="submit">Aplicar ao lote</button>
            </form>
            <noscript><p>As ações em lote exigem JavaScript. As ações individuais continuam disponíveis.</p></noscript>
        </div>

        <div class="estado-consulta">
            <span x-cloak x-show="buscaCarregando" role="status" aria-live="polite">Buscando…</span>
            <span x-cloak x-show="erroBusca" x-text="erroBusca" class="falha-busca" role="alert" aria-atomic="true"></span>
        </div>
        <div id="resultados-busca" x-ref="resultados" x-bind:aria-busy="buscaCarregando.toString()">@include('notas._resultados')</div>

        @unless ($secaoArquivadas)
        <x-modal name="criar-nota" :show="$errors->criacaoNota->isNotEmpty()" max-width="xl" focusable titulo-id="titulo-modal-criacao">
            <form method="POST" action="{{ route('notas.store') }}" class="formulario-nota" x-bind:class="classeAparencia(criacao)" x-bind:style="estiloAparencia(criacao)" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                @csrf
                <div class="cabecalho-modal"><div><span class="etiqueta">NOVA NOTA</span><h2 id="titulo-modal-criacao">Registre uma ideia</h2></div><button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'criar-nota')" aria-label="Cancelar e fechar"><x-icone nome="fechar" /></button></div>
                <label class="grupo-campo">Tipo
                    <select name="tipo_conteudo" x-model="criacao.tipo_conteudo"><option value="texto">Texto</option><option value="lista">Lista de tarefas</option></select>
                </label>
                <div class="grupo-campo"><label for="criar-titulo">Título <span>Opcional</span></label><input id="criar-titulo" name="titulo" autofocus type="text" maxlength="255" value="{{ old('titulo') }}" autocomplete="off"></div>
                <div class="grupo-campo"><label for="criar-descricao">Descrição de apoio <span>Opcional</span></label><textarea id="criar-descricao" name="descricao" rows="4" maxlength="10000">{{ old('descricao') }}</textarea></div>
                <fieldset class="editor-lista" x-show="criacao.tipo_conteudo === 'lista'"><legend>Itens da lista</legend>
                    <template x-for="(item, indice) in criacao.itens" :key="item.chave"><div class="linha-item">
                        <input type="text" maxlength="500" x-model="item.texto" x-bind:name="'itens['+indice+'][texto]'" aria-label="Texto do item">
                        <input type="hidden" x-bind:name="'itens['+indice+'][concluido]'" x-bind:value="item.concluido ? 1 : 0">
                        <button type="button" x-on:click="moverItem(criacao.itens, indice, -1)" aria-label="Mover item para cima">↑</button>
                        <button type="button" x-on:click="moverItem(criacao.itens, indice, 1)" aria-label="Mover item para baixo">↓</button>
                        <button type="button" x-on:click="criacao.itens.splice(indice,1)" aria-label="Remover item">Remover</button>
                    </div></template>
                    <button type="button" x-on:click="adicionarItem(criacao.itens)">Adicionar item</button>
                    <noscript><label>Primeiro item <input name="itens[0][texto]" maxlength="500"></label></noscript>
                </fieldset>
                @foreach ($errors->criacaoNota->all() as $erro)<p class="erro-campo">{{ $erro }}</p>@endforeach
                <x-seletor-aparencia id="criar-aparencia" modelo="criacao" :tipo-selecionado="$criacao['tipo_aparencia']" :cor-selecionada="$criacao['cor']" :fundo-selecionado="$criacao['fundo']" />
                <div class="acoes-modal"><button type="button" class="botao-secundario" x-on:click="$dispatch('close-modal', 'criar-nota')">Cancelar</button><button type="submit" class="botao-principal" x-bind:disabled="enviando" x-text="enviando ? 'Criando…' : 'Criar nota'"></button></div>
            </form>
        </x-modal>
        @endunless

        <x-modal name="editar-nota" max-width="xl" focusable titulo-id="titulo-modal-edicao">
            <form class="formulario-nota" x-bind:class="classeAparencia(edicao)" x-bind:style="estiloAparencia(edicao)" x-on:submit.prevent="salvarEdicao">
                <div class="cabecalho-modal"><div><span class="etiqueta">EDITAR NOTA</span><h2 id="titulo-modal-edicao">Revise sua ideia</h2></div><button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'editar-nota')" aria-label="Cancelar e fechar"><x-icone nome="fechar" /></button></div>
                <div x-show="carregandoEdicao" class="carregando-nota" role="status">Abrindo nota…</div>
                <div x-show="erroCarregamento" x-text="erroCarregamento" class="erro-geral" role="alert"></div>
                <div x-show="conflitoEdicao" class="conflito-edicao" role="alert">
                    <h3>Outra versão foi salva</h3><p>Compare antes de substituir. A versão atual do servidor é:</p>
                    <pre x-text="resumoConflitoAtual()"></pre>
                    <button type="button" x-on:click="usarVersaoAtual">Usar versão atual</button>
                    <button type="button" x-on:click="tentarVersaoLocal">Tentar salvar minha versão</button>
                </div>
                <div x-show="!carregandoEdicao && !erroCarregamento && !conflitoEdicao">
                    <p class="ajuda-formulario" x-text="edicao.papel === 'editor' ? 'Você edita esta nota compartilhada como Editor.' : 'As alterações usam controle de revisão para evitar perdas.'"></p>
                    <div class="grupo-campo"><label for="editar-titulo">Título <span>Opcional</span></label><input id="editar-titulo" type="text" maxlength="255" x-model="edicao.titulo" autocomplete="off"></div>
                    <div class="grupo-campo"><label for="editar-descricao">Descrição de apoio <span>Opcional</span></label><textarea id="editar-descricao" rows="4" maxlength="10000" x-model="edicao.descricao"></textarea></div>
                    <fieldset class="editor-lista" x-show="edicao.tipo_conteudo === 'lista'"><legend>Itens da lista</legend>
                        <template x-for="(item, indice) in edicao.itens" :key="item.chave || item.id || indice"><div class="linha-item">
                            <label><input type="checkbox" x-model="item.concluido"> Concluído</label>
                            <input type="text" maxlength="500" x-model="item.texto" aria-label="Texto do item">
                            <button type="button" x-on:click="moverItem(edicao.itens, indice, -1)" aria-label="Mover item para cima">↑</button>
                            <button type="button" x-on:click="moverItem(edicao.itens, indice, 1)" aria-label="Mover item para baixo">↓</button>
                            <button type="button" x-on:click="edicao.itens.splice(indice,1)">Remover</button>
                        </div></template>
                        <button type="button" x-on:click="adicionarItem(edicao.itens)">Adicionar item</button>
                    </fieldset>
                    <template x-for="campo in ['titulo','descricao','tipo_conteudo','itens','tipo_aparencia','cor','fundo','revisao']"><template x-for="erro in (errosEdicao[campo] ?? [])"><p class="erro-campo" x-text="erro"></p></template></template>
                    <x-seletor-aparencia id="editar-aparencia" modelo="edicao" />
                    <div class="acoes-modal"><button type="button" class="botao-secundario" x-on:click="$dispatch('close-modal', 'editar-nota')">Cancelar</button><button type="submit" class="botao-principal" x-bind:disabled="salvandoEdicao" x-text="salvandoEdicao ? 'Salvando…' : 'Salvar alterações'"></button></div>
                </div>
            </form>
        </x-modal>

        <x-modal name="consultar-nota" max-width="lg" focusable titulo-id="titulo-modal-leitura">
            <div class="formulario-nota leitura-nota" x-bind:class="classeAparencia(leitura)" x-bind:style="estiloAparencia(leitura)">
                <div class="cabecalho-modal"><div><span class="etiqueta">SOMENTE LEITURA</span><h2 id="titulo-modal-leitura">Consultar nota</h2></div><button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'consultar-nota')" aria-label="Fechar consulta"><x-icone nome="fechar" /></button></div>
                <section class="campo-leitura"><h3>Título</h3><p x-text="leitura.titulo || 'Sem título'"></p></section>
                <section class="campo-leitura"><h3>Descrição</h3><p x-text="leitura.descricao || 'Sem descrição'"></p></section>
                <ul class="lista-leitura" x-show="leitura.tipo_conteudo === 'lista'"><template x-for="item in leitura.itens"><li x-text="(item.concluido ? 'Concluído: ' : 'Pendente: ') + item.texto"></li></template></ul>
                <p class="ajuda-formulario">Seu papel permite consultar e exportar esta nota, sem modificá-la.</p>
                <div class="acoes-modal"><button type="button" class="botao-principal" x-on:click="$dispatch('close-modal', 'consultar-nota')">Fechar</button></div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
