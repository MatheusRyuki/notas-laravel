@props(['action', 'termo' => '', 'placeholder' => 'Buscar nas suas notas', 'ordem' => 'atualizacao', 'etiquetasSelecionadas' => []])

<form method="GET" action="{{ $action }}" class="campo-busca" role="search" x-on:submit.prevent="buscarAgora">
    <x-icone nome="busca" class="icone icone-busca" />
    <label for="busca" class="sr-only">Buscar por título ou descrição</label>
    <input
        id="busca"
        x-ref="busca"
        name="q"
        type="search"
        maxlength="100"
        placeholder="{{ $placeholder }}"
        value="{{ $termo }}"
        x-model="termoBusca"
        x-on:input="agendarBusca"
        autocomplete="off"
    >
    <input type="hidden" name="ordem" value="{{ $ordem }}">
    @foreach($etiquetasSelecionadas as $etiquetaId)<input type="hidden" name="etiquetas[]" value="{{ $etiquetaId }}">@endforeach
    <button x-cloak x-show="termoBusca" type="button" class="limpar-busca" x-on:click="limparBusca">Limpar</button>
    <noscript><button type="submit" class="limpar-busca">Buscar</button></noscript>
</form>
