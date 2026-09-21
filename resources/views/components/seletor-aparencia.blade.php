@props([
    'tipoSelecionado' => 'cor',
    'corSelecionada' => 'padrao',
    'fundoSelecionado' => 'folhas',
    'modelo' => null,
    'id' => 'aparencia-nota',
])

<section class="seletor-aparencia" aria-labelledby="{{ $id }}-titulo">
    <h3 id="{{ $id }}-titulo">Aparência</h3>

    <fieldset class="tipos-aparencia">
        <legend class="sr-only">Tipo de aparência</legend>
        @foreach (\App\Enums\TipoAparencia::cases() as $tipo)
            <div>
                <input
                    class="radio-aparencia"
                    tabindex="-1"
                    type="radio"
                    id="{{ $id }}-tipo-{{ $tipo->value }}"
                    name="tipo_aparencia"
                    value="{{ $tipo->value }}"
                    @if ($modelo) x-model="{{ $modelo }}.tipo_aparencia" @else @checked($tipoSelecionado === $tipo->value) @endif
                >
                <label
                    for="{{ $id }}-tipo-{{ $tipo->value }}"
                    tabindex="0"
                    role="radio"
                    @if ($modelo) x-bind:aria-checked="{{ $modelo }}.tipo_aparencia === '{{ $tipo->value }}'" @else aria-checked="{{ $tipoSelecionado === $tipo->value ? 'true' : 'false' }}" @endif
                    x-on:keydown="if ($event.key === ' ' || $event.key === 'Enter') { $event.preventDefault(); $el.previousElementSibling.click(); }"
                >{{ $tipo === \App\Enums\TipoAparencia::Cor ? 'Cores' : 'Fundos' }}</label>
            </div>
        @endforeach
    </fieldset>

    <div @if ($modelo) x-show="{{ $modelo }}.tipo_aparencia === 'cor'" @elseif ($tipoSelecionado !== 'cor') hidden @endif>
        <x-seletor-cor
            name="cor"
            :id="$id.'-cor'"
            :selecionada="$corSelecionada"
            :modelo="$modelo ? $modelo.'.cor' : null"
        />
    </div>

    <fieldset
        class="seletor-fundo"
        @if ($modelo) x-show="{{ $modelo }}.tipo_aparencia === 'imagem'" x-cloak @elseif ($tipoSelecionado !== 'imagem') hidden @endif
    >
        <legend>Escolha um fundo</legend>
        <div class="opcoes-fundo">
            @foreach (\App\Enums\FundoNota::cases() as $fundo)
                <div class="opcao-fundo">
                    <input
                        class="radio-aparencia"
                        tabindex="-1"
                        type="radio"
                        id="{{ $id }}-fundo-{{ $fundo->value }}"
                        name="fundo"
                        value="{{ $fundo->value }}"
                        @if ($modelo) x-model="{{ $modelo }}.fundo" @else @checked($fundoSelecionado === $fundo->value) @endif
                    >
                    <label
                        for="{{ $id }}-fundo-{{ $fundo->value }}"
                        tabindex="0"
                        role="radio"
                        @if ($modelo) x-bind:aria-checked="{{ $modelo }}.fundo === '{{ $fundo->value }}'" @else aria-checked="{{ $fundoSelecionado === $fundo->value ? 'true' : 'false' }}" @endif
                        x-on:keydown="if ($event.key === ' ' || $event.key === 'Enter') { $event.preventDefault(); $el.previousElementSibling.click(); }"
                    >
                        <span class="miniatura-fundo" style="background-image:url('{{ asset($fundo->caminho()) }}')" aria-hidden="true"></span>
                        <span>{{ $fundo->rotulo() }}</span>
                        <span class="marca-selecionada" aria-hidden="true">✓</span>
                    </label>
                </div>
            @endforeach
        </div>
    </fieldset>
</section>