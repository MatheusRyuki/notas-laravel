@props([
    'name' => 'cor',
    'selecionada' => 'padrao',
    'modelo' => null,
    'id' => 'cor-nota',
])

<fieldset class="seletor-cor">
    <legend>Cor de fundo</legend>
    <div class="opcoes-cor">
        @foreach (\App\Enums\CorNota::cases() as $opcao)
            <div class="opcao-cor">
                <input
                    class="radio-aparencia"
                    tabindex="-1"
                    type="radio"
                    id="{{ $id }}-{{ $opcao->value }}"
                    name="{{ $name }}"
                    value="{{ $opcao->value }}"
                    @if ($modelo) x-model="{{ $modelo }}" @else @checked($selecionada === $opcao->value) @endif
                >
                <label
                    for="{{ $id }}-{{ $opcao->value }}"
                    tabindex="0"
                    role="radio"
                    @if ($modelo) x-bind:aria-checked="{{ $modelo }} === '{{ $opcao->value }}'" @else aria-checked="{{ $selecionada === $opcao->value ? 'true' : 'false' }}" @endif
                    x-on:keydown="if ($event.key === ' ' || $event.key === 'Enter') { $event.preventDefault(); $el.previousElementSibling.click(); }"
                >
                    <span class="amostra-cor cor-amostra--{{ $opcao->value }}" aria-hidden="true"></span>
                    <span>{{ $opcao->rotulo() }}</span>
                    <span class="marca-selecionada" aria-hidden="true">✓</span>
                </label>
            </div>
        @endforeach
    </div>
</fieldset>