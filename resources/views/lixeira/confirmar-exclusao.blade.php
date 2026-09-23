<x-app-layout>
    <div class="confirmacao-pagina" x-data x-init="$nextTick(() => $refs.cancelar.focus())" x-on:keydown.escape.window="window.location.href = @js(route('lixeira.index', $consulta))">
        <section class="confirmacao-exclusao" aria-labelledby="titulo-confirmacao" aria-describedby="descricao-confirmacao">
            <span class="etiqueta">EXCLUSÃO DEFINITIVA</span>
            <h1 id="titulo-confirmacao">Excluir esta nota para sempre?</h1>
            <p id="descricao-confirmacao">A nota <strong>{{ $nota->titulo ?: \Illuminate\Support\Str::limit($nota->descricao, 80) }}</strong> será removida definitivamente. Esta ação não poderá ser desfeita.</p>
            @if ($nota->titulo && $nota->descricao)
                <blockquote>{{ \Illuminate\Support\Str::limit($nota->descricao, 140) }}</blockquote>
            @endif
            <p class="aviso-irreversivel">Os fundos compartilhados do catálogo permanecerão disponíveis para outras notas.</p>
            <div class="acoes-confirmacao">
                <a href="{{ route('lixeira.index', $consulta) }}" class="botao-secundario" x-ref="cancelar">Cancelar</a>
                <form method="POST" action="{{ route('lixeira.destruir', $nota->id) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf
                    @foreach($consulta as $nome => $valor)
                        @foreach((array) $valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach
                    @endforeach
                    @method('DELETE')
                    <button type="submit" class="botao-perigo" x-bind:disabled="enviando" x-text="enviando ? 'Excluindo…' : 'Excluir definitivamente'">Excluir definitivamente</button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>