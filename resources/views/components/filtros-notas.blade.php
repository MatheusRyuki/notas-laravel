@props([
    'action',
    'termo' => '',
    'ordem' => 'atualizacao',
    'opcoesOrdem' => [],
    'etiquetas' => collect(),
    'etiquetasSelecionadas' => [],
    'secao' => 'notas',
    'permiteGerenciar' => false,
])

@php
$temEtiquetas = $etiquetas->isNotEmpty();
$idOrdem = 'ordem-'.$secao;
$urlGerenciamento = route('notas.inicio').'#gerenciar-etiquetas';
@endphp

<div class="painel-filtros">
    <form method="GET" action="{{ $action }}" class="form-filtros">
        @if($termo)<input type="hidden" name="q" value="{{ $termo }}">@endif

        <div class="campo-filtro">
            <label for="{{ $idOrdem }}">Ordenar</label>
            <select id="{{ $idOrdem }}" name="ordem" class="controle-select">
                @foreach($opcoesOrdem as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($ordem === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>

        @if($temEtiquetas)
            <fieldset class="filtro-etiquetas">
                <legend>Filtrar por qualquer etiqueta</legend>
                <div class="opcoes-filtro-etiquetas">
                    @foreach($etiquetas as $etiqueta)
                        <label>
                            <input type="checkbox" name="etiquetas[]" value="{{ $etiqueta->id }}" @checked(in_array($etiqueta->id, $etiquetasSelecionadas))>
                            <span>{{ $etiqueta->nome }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @else
            <div class="filtro-sem-etiquetas">
                <strong>Etiquetas</strong>
                <span>Nenhuma etiqueta criada.</span>
                <a href="{{ $permiteGerenciar ? '#gerenciar-etiquetas' : $urlGerenciamento }}">Criar ou gerenciar</a>
            </div>
        @endif

        <button type="submit" class="botao-principal botao-aplicar-filtros">Aplicar filtros</button>
    </form>

    @if($permiteGerenciar)
        <details id="gerenciar-etiquetas" class="gerenciador-etiquetas">
            <summary class="botao-secundario"><x-icone nome="etiqueta" /> Gerenciar etiquetas</summary>
            <div class="painel-gerenciador-etiquetas">
                <form method="POST" action="{{ route('etiquetas.store') }}" class="form-etiqueta">
                    @csrf
                    <label for="nova-etiqueta-{{ $secao }}">Nova etiqueta</label>
                    <div class="linha-etiqueta">
                        <input id="nova-etiqueta-{{ $secao }}" name="nome" maxlength="60" required>
                        <button type="submit" class="botao-principal botao-compacto">Criar</button>
                    </div>
                </form>

                @if($temEtiquetas)
                    <div class="lista-gerenciamento-etiquetas">
                        @foreach($etiquetas as $etiqueta)
                            <div class="item-gerenciamento-etiqueta">
                                <form method="POST" action="{{ route('etiquetas.update', $etiqueta) }}" class="form-renomear-etiqueta">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="etiqueta-{{ $secao }}-{{ $etiqueta->id }}">Nome da etiqueta</label>
                                    <input id="etiqueta-{{ $secao }}-{{ $etiqueta->id }}" name="nome" value="{{ $etiqueta->nome }}" maxlength="60" required>
                                    <button type="submit" class="botao-secundario botao-compacto">Renomear</button>
                                </form>
                                <form method="POST" action="{{ route('etiquetas.destroy', $etiqueta) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="botao-destrutivo botao-compacto">Excluir</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="estado-painel-vazio">Crie a primeira etiqueta para organizar e filtrar suas notas.</p>
                @endif
            </div>
        </details>
    @else
        <a class="botao-secundario atalho-gerenciar-etiquetas" href="{{ $urlGerenciamento }}"><x-icone nome="etiqueta" /> Gerenciar etiquetas</a>
    @endif
</div>
