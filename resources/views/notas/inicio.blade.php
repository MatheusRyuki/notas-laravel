@php
$criacao = [
    'tipo_aparencia' => old('tipo_aparencia', \App\Enums\TipoAparencia::Cor->value),
    'cor' => old('cor', \App\Enums\CorNota::Padrao->value),
    'fundo' => old('fundo', \App\Enums\FundoNota::cases()[0]->value),
];
$urlsFundos = collect(\App\Enums\FundoNota::cases())
    ->mapWithKeys(fn ($fundo) => [$fundo->value => asset($fundo->caminho())])
    ->all();
@endphp

<x-app-layout>
    <div x-data="notasApp(@js($criacao), @js($urlsFundos), @js(['termo' => $termo ?? '', 'secao' => $secao, 'url' => $secaoArquivadas ? route('notas.arquivadas') : route('notas.inicio')]))" x-init="iniciarBusca()">
        @if (session('sucesso'))
            <div class="aviso-sucesso" role="status">{{ session('sucesso') }}</div>
        @endif

        @if ($errors->arquivamentoNota->isNotEmpty())
            <div class="aviso-erro" role="alert">{{ $errors->arquivamentoNota->first('arquivada') }}</div>
        @endif

        <div class="titulo-notas">
            <div>
                <span class="etiqueta">{{ $secaoArquivadas ? 'NOTAS FORA DA TELA PRINCIPAL' : 'SEU ESPAÇO PESSOAL' }}</span>
                <h1>{{ $secaoArquivadas ? 'Arquivadas' : 'Minhas notas' }}</h1>
                <p>{{ $secaoArquivadas ? 'Notas guardadas continuam disponíveis para consulta e edição.' : 'Um lugar para organizar o que passa pela sua cabeça.' }}</p>
            </div>
            <span class="selo-etapa">Etapa 07 · Busca</span>
        </div>

        <div class="ferramentas-notas">
            <x-busca-notas :action="$secaoArquivadas ? route('notas.arquivadas') : route('notas.inicio')" :termo="$termo" :placeholder="$secaoArquivadas ? 'Buscar nas arquivadas' : 'Buscar nas suas notas'" />
            @unless ($secaoArquivadas)
                <button class="nova-nota" type="button" x-on:click="$dispatch('open-modal', 'criar-nota')"><span aria-hidden="true">＋</span> Criar nota</button>
            @endunless
        </div>

        <div class="estado-consulta" aria-live="polite" aria-atomic="true">
            <span x-cloak x-show="buscaCarregando">Buscando…</span>
            <span x-cloak x-show="erroBusca" x-text="erroBusca" class="falha-busca" role="alert"></span>
        </div>
        <div id="resultados-busca" x-ref="resultados" x-bind:aria-busy="buscaCarregando.toString()">
            @include('notas._resultados')
        </div>

        <div class="proximos-passos"><span class="indicador"></span><p>A busca consulta título e descrição dentro desta seção.</p></div>

        @unless ($secaoArquivadas)
        <x-modal name="criar-nota" :show="$errors->criacaoNota->isNotEmpty()" max-width="xl" focusable titulo-id="titulo-modal-criacao">
            <form method="POST" action="{{ route('notas.store') }}" class="formulario-nota" x-bind:class="classeAparencia(criacao)" x-bind:style="estiloAparencia(criacao)" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                @csrf
                <input type="hidden" name="q" value="{{ $termo ?? '' }}">
                <div class="cabecalho-modal">
                    <div><span class="etiqueta">NOVA NOTA</span><h2 id="titulo-modal-criacao">Registre uma ideia</h2></div>
                    <button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'criar-nota')" aria-label="Cancelar e fechar">×</button>
                </div>
                <p class="ajuda-formulario">Preencha pelo menos um campo. Título: até 255 caracteres. Descrição: até 10.000.</p>
                <div class="grupo-campo">
                    <label for="criar-titulo">Título <span>Opcional</span></label>
                    <input id="criar-titulo" name="titulo" autofocus type="text" maxlength="255" value="{{ old('titulo') }}" autocomplete="off">
                    @foreach ($errors->criacaoNota->get('titulo') as $erro)<p class="erro-campo">{{ $erro }}</p>@endforeach
                </div>
                <div class="grupo-campo">
                    <label for="criar-descricao">Descrição <span>Opcional</span></label>
                    <textarea id="criar-descricao" name="descricao" rows="6" maxlength="10000">{{ old('descricao') }}</textarea>
                    @foreach ($errors->criacaoNota->get('descricao') as $erro)<p class="erro-campo">{{ $erro }}</p>@endforeach
                </div>
                <x-seletor-aparencia id="criar-aparencia" modelo="criacao" :tipo-selecionado="$criacao['tipo_aparencia']" :cor-selecionada="$criacao['cor']" :fundo-selecionado="$criacao['fundo']" />
                @foreach (['tipo_aparencia', 'cor', 'fundo'] as $campo)
                    @foreach ($errors->criacaoNota->get($campo) as $erro)<p class="erro-campo">{{ $erro }}</p>@endforeach
                @endforeach
                <div class="acoes-modal">
                    <button type="button" class="botao-secundario" x-on:click="$dispatch('close-modal', 'criar-nota')">Cancelar</button>
                    <button type="submit" class="botao-principal" x-bind:disabled="enviando" x-text="enviando ? 'Criando…' : 'Criar nota'"></button>
                </div>
            </form>
        </x-modal>
        @endunless

        <x-modal name="editar-nota" max-width="xl" titulo-id="titulo-modal-edicao">
            <form class="formulario-nota" x-bind:class="classeAparencia(edicao)" x-bind:style="estiloAparencia(edicao)" x-on:submit.prevent="salvarEdicao">
                <div class="cabecalho-modal">
                    <div><span class="etiqueta">EDITAR NOTA</span><h2 id="titulo-modal-edicao">Revise sua ideia</h2></div>
                    <button type="button" class="fechar-modal" x-on:click="$dispatch('close-modal', 'editar-nota')" aria-label="Cancelar e fechar">×</button>
                </div>

                <div x-show="carregandoEdicao" class="carregando-nota" role="status">Abrindo nota…</div>
                <div x-show="erroCarregamento" x-text="erroCarregamento" class="erro-geral" role="alert"></div>

                <div x-show="!carregandoEdicao && !erroCarregamento">
                    <p class="ajuda-formulario">Preencha pelo menos um campo. Título: até 255 caracteres. Descrição: até 10.000.</p>
                    <div class="grupo-campo">
                        <label for="editar-titulo">Título <span>Opcional</span></label>
                        <input id="editar-titulo" type="text" maxlength="255" x-model="edicao.titulo" autocomplete="off">
                        <template x-for="erro in (errosEdicao.titulo ?? [])"><p class="erro-campo" x-text="erro"></p></template>
                    </div>
                    <div class="grupo-campo">
                        <label for="editar-descricao">Descrição <span>Opcional</span></label>
                        <textarea id="editar-descricao" rows="6" maxlength="10000" x-model="edicao.descricao"></textarea>
                        <template x-for="erro in (errosEdicao.descricao ?? [])"><p class="erro-campo" x-text="erro"></p></template>
                    </div>
                    <x-seletor-aparencia id="editar-aparencia" modelo="edicao" />
                    <template x-for="campo in ['tipo_aparencia', 'cor', 'fundo']">
                        <template x-for="erro in (errosEdicao[campo] ?? [])"><p class="erro-campo" x-text="erro"></p></template>
                    </template>
                    <div class="acoes-modal">
                        <button type="button" class="botao-secundario" x-on:click="$dispatch('close-modal', 'editar-nota')">Cancelar</button>
                        <button type="submit" class="botao-principal" x-bind:disabled="salvandoEdicao" x-text="salvandoEdicao ? 'Salvando…' : 'Salvar alterações'"></button>
                    </div>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>