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
                    type="radio"
                    id="{{ $id }}-{{ $opcao->value }}"
                    name="{{ $name }}"
                    value="{{ $opcao->value }}"
                    @if ($modelo) x-model="{{ $modelo }}" @else @checked($selecionada === $opcao->value) @endif
                >
                <label
                    for="{{ $id }}-{{ $opcao->value }}"
                >
                    <span class="amostra-cor cor-amostra--{{ $opcao->value }}" aria-hidden="true"></span>
                    <span>{{ $opcao->rotulo() }}</span>
                    <span class="marca-selecionada" aria-hidden="true"><x-icone nome="check" /></span>
                </label>
            </div>
        @endforeach
    </div>
</fieldset>