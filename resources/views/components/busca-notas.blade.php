@props(['action', 'termo' => '', 'placeholder' => 'Buscar nas suas notas'])

<form method="GET" action="{{ $action }}" class="campo-busca" role="search" x-on:submit.prevent="buscarAgora">
    <x-icone nome="busca" class="icone icone-busca" />
    <label for="busca" class="sr-only">Buscar por título ou descrição</label>
    <input
        id="busca"
        name="q"
        type="search"
        maxlength="100"
        value="{{ $termo }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        x-ref="busca"
        x-model="termoBusca"
        x-on:input="agendarBusca"
    >
    <a
        href="{{ $action }}"
        class="limpar-busca"
        aria-label="Limpar busca"
        @if($termo === null || $termo === '') hidden @endif
        x-bind:hidden="termoBusca.length === 0"
        x-on:click.prevent="limparBusca"
    >Limpar</a>
</form>