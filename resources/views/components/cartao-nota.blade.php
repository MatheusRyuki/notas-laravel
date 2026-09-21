@props(['nota', 'emArquivadas' => false, 'emLixeira' => false])

@php
$cor = $nota->cor?->value ?? \App\Enums\CorNota::Padrao->value;
$fundo = $nota->tipo_aparencia === \App\Enums\TipoAparencia::Imagem ? $nota->fundo() : null;
$classeAparencia = $fundo ? 'aparencia-imagem' : 'cor-nota--'.$cor;
$termoBusca = trim((string) request('q', ''));
$identificacao = $nota->titulo ?: \Illuminate\Support\Str::limit($nota->descricao ?: 'sem título', 45);
@endphp

<article
    class="cartao-nota {{ $classeAparencia }}"
    data-tipo-aparencia="{{ $fundo ? 'imagem' : 'cor' }}"
    data-cor="{{ $fundo ? '' : $cor }}"
    data-arquivada="{{ $nota->arquivada ? 'true' : 'false' }}"
    data-removida="{{ $nota->trashed() ? 'true' : 'false' }}"
    @if($fundo) data-fundo="{{ $fundo->value }}" style="--imagem-nota:url('{{ asset($fundo->caminho()) }}')" @endif
    @if($nota->fixada) data-fixada="true" @endif
>
    @if ($nota->fixada)
        <span class="estado-fixada"><span aria-hidden="true">●</span> Fixada</span>
    @endif
    @if ($emLixeira && $nota->arquivada)
        <span class="estado-origem">Antes: Arquivadas</span>
    @endif
    <button type="button" class="conteudo-nota" x-on:click="{{ $emLixeira ? 'abrirLeitura('.Illuminate\Support\Js::from(route('lixeira.show', $nota->id)).')' : 'abrirEdicao('.Illuminate\Support\Js::from(route('notas.show', $nota)).')' }}" aria-label="{{ $emLixeira ? 'Consultar' : 'Abrir' }} nota: {{ $identificacao }}">
        @if ($nota->titulo)
            <h3>{{ $nota->titulo }}</h3>
        @else
            <span class="sem-titulo">SEM TÍTULO</span>
        @endif
        @if ($nota->descricao)
            <p>{{ $nota->descricao }}</p>
        @endif
    </button>
    <footer>
        <time datetime="{{ ($emLixeira ? $nota->deleted_at : $nota->updated_at)->toIso8601String() }}">{{ ($emLixeira ? $nota->deleted_at : $nota->updated_at)->format('d/m/Y H:i') }}</time>
        <div class="acoes-cartao">
            @if ($emLixeira)
                <form method="POST" action="{{ route('lixeira.restaurar', $nota->id) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf
                    <input type="hidden" name="q" value="{{ $termoBusca }}">
                    @method('PATCH')
                    <button type="submit" class="botao-restaurar" x-bind:disabled="enviando" x-on:click.stop aria-label="Restaurar nota {{ $identificacao }}"><span aria-hidden="true">↶</span> Restaurar</button>
                </form>
                <a class="botao-excluir" href="{{ route('lixeira.confirmar-exclusao', ['nota' => $nota->id, 'q' => $termoBusca ?: null]) }}" aria-label="Excluir definitivamente nota {{ $identificacao }}"><span aria-hidden="true">×</span> Excluir</a>
            @else
                @unless ($emArquivadas)
                    <form method="POST" action="{{ route('notas.fixacao', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                        @csrf
                        <input type="hidden" name="q" value="{{ $termoBusca }}">
                        @method('PATCH')
                        <input type="hidden" name="fixada" value="{{ $nota->fixada ? '0' : '1' }}">
                        <button type="submit" class="botao-fixacao" x-bind:disabled="enviando" x-on:click.stop aria-label="{{ $nota->fixada ? 'Desafixar' : 'Fixar' }} nota {{ $identificacao }}" aria-pressed="{{ $nota->fixada ? 'true' : 'false' }}"><span aria-hidden="true">{{ $nota->fixada ? '◆' : '◇' }}</span><span>{{ $nota->fixada ? 'Desafixar' : 'Fixar' }}</span></button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('notas.arquivamento', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf
                    <input type="hidden" name="q" value="{{ $termoBusca }}">
                    @method('PATCH')
                    <input type="hidden" name="arquivada" value="{{ $emArquivadas ? '0' : '1' }}">
                    <button type="submit" class="botao-arquivamento" x-bind:disabled="enviando" x-on:click.stop aria-label="{{ $emArquivadas ? 'Desarquivar' : 'Arquivar' }} nota {{ $identificacao }}"><span aria-hidden="true">{{ $emArquivadas ? '↥' : '▱' }}</span><span>{{ $emArquivadas ? 'Desarquivar' : 'Arquivar' }}</span></button>
                </form>
                <form method="POST" action="{{ route('notas.mover-lixeira', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf
                    <input type="hidden" name="q" value="{{ $termoBusca }}">
                    @method('DELETE')
                    <button type="submit" class="botao-lixeira" x-bind:disabled="enviando" x-on:click.stop aria-label="Mover nota {{ $identificacao }} para a lixeira"><span aria-hidden="true">♲</span><span>Lixeira</span></button>
                </form>
            @endif
        </div>
    </footer>
</article>