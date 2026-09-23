@props(['nota', 'emArquivadas' => false, 'emLixeira' => false, 'etiquetas' => collect()])

@php
$cor = $nota->cor?->value ?? \App\Enums\CorNota::Padrao->value;
$fundo = $nota->tipo_aparencia === \App\Enums\TipoAparencia::Imagem ? $nota->fundo() : null;
$classeAparencia = $fundo ? 'aparencia-imagem' : 'cor-nota--'.$cor;
$identificacao = $nota->titulo ?: \Illuminate\Support\Str::limit($nota->descricao ?: 'sem título', 45);
$proprietaria = $nota->usuario_id === auth()->id();
$papel = $proprietaria ? 'Proprietário' : ($nota->papelDe(auth()->user())?->rotulo() ?? 'Sem acesso');
$consulta = collect(request()->only(['q', 'ordem', 'etiquetas']))->reject(fn ($valor) => $valor === null || $valor === '' || $valor === [])->all();
$etiquetasAtuais = $nota->relationLoaded('etiquetas') ? $nota->etiquetas->pluck('id')->all() : [];
@endphp

<article
    class="cartao-nota {{ $classeAparencia }}"
    data-nota-id="{{ $nota->id }}"
    data-tipo-aparencia="{{ $fundo ? 'imagem' : 'cor' }}"
    data-cor="{{ $fundo ? '' : $cor }}"
    data-arquivada="{{ $nota->arquivada ? 'true' : 'false' }}"
    data-removida="{{ $nota->trashed() ? 'true' : 'false' }}"
    @if($fundo) data-fundo="{{ $fundo->value }}" style="--imagem-nota:url('{{ asset($fundo->caminho()) }}')" @endif
    @if($nota->fixada) data-fixada="true" @endif
>
    <label class="selecao-cartao" x-cloak x-show="modoSelecao">
        <input type="checkbox" value="{{ $nota->id }}" x-model="selecionadas">
        <span>Selecionar {{ $identificacao }}</span>
    </label>

    <div class="metadados-cartao">
        @if ($nota->fixada)<span class="estado-fixada"><x-icone nome="fixada" /> Fixada</span>@endif
        @unless($proprietaria)<span class="estado-compartilhada">{{ $papel }} · {{ $nota->usuario->name }}</span>@endunless
        @if ($emLixeira && $nota->arquivada)<span class="estado-origem">Antes: Arquivadas</span>@endif
        @if ($nota->tipo_conteudo === \App\Enums\TipoNota::Lista)<span class="estado-origem">Lista</span>@endif
    </div>

    <button type="button" class="conteudo-nota" x-on:click="if (!modoSelecao) abrirNota(@js($emLixeira ? route('lixeira.show', $nota->id) : route('notas.show', $nota)))" aria-label="{{ $emLixeira ? 'Consultar' : 'Abrir' }} nota: {{ $identificacao }}">
        @if ($nota->titulo)<h3>{{ $nota->titulo }}</h3>@else<span class="sem-titulo">SEM TÍTULO</span>@endif
        @if ($nota->descricao)<p>{{ $nota->descricao }}</p>@endif
        @if ($nota->tipo_conteudo === \App\Enums\TipoNota::Lista)
            <ul class="lista-cartao">
                @foreach($nota->itens->take(6) as $item)
                    <li class="{{ $item->concluido ? 'concluido' : '' }}"><span aria-hidden="true">{{ $item->concluido ? '☑' : '☐' }}</span> {{ $item->texto }}</li>
                @endforeach
            </ul>
        @endif
        @if($etiquetasAtuais)
            <span class="etiquetas-cartao">
                @foreach($nota->etiquetas as $etiqueta)<span>{{ $etiqueta->nome }}</span>@endforeach
            </span>
        @endif
    </button>

    <footer>
        <time datetime="{{ ($emLixeira ? $nota->deleted_at : $nota->updated_at)->toIso8601String() }}">{{ ($emLixeira ? $nota->deleted_at : $nota->updated_at)->format('d/m/Y H:i') }}</time>
        <div class="acoes-cartao">
            @if ($emLixeira)
                <form method="POST" action="{{ route('lixeira.restaurar', $nota->id) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf @method('PATCH')
                    @foreach($consulta as $nome => $valor)
                        @foreach((array) $valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach
                    @endforeach
                    <button type="submit" class="botao-restaurar" x-bind:disabled="enviando" x-on:click.stop aria-label="Restaurar nota {{ $identificacao }}"><x-icone nome="restaurar" /> Restaurar</button>
                </form>
                <a class="botao-excluir" href="{{ route('lixeira.confirmar-exclusao', ['nota' => $nota->id] + $consulta) }}" aria-label="Excluir definitivamente nota {{ $identificacao }}"><x-icone nome="excluir" /> Excluir</a>
            @elseif($proprietaria)
                @unless ($emArquivadas)
                    <form method="POST" action="{{ route('notas.fixacao', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                        @csrf @method('PATCH')
                        @foreach($consulta as $nome => $valor)@foreach((array) $valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach @endforeach
                        <input type="hidden" name="fixada" value="{{ $nota->fixada ? '0' : '1' }}">
                        <button type="submit" class="botao-fixacao" x-bind:disabled="enviando" x-on:click.stop aria-label="{{ $nota->fixada ? 'Desafixar' : 'Fixar' }} nota {{ $identificacao }}" aria-pressed="{{ $nota->fixada ? 'true' : 'false' }}"><x-icone :nome="$nota->fixada ? 'fixada' : 'fixar'" /><span>{{ $nota->fixada ? 'Desafixar' : 'Fixar' }}</span></button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('notas.arquivamento', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf @method('PATCH')
                    @foreach($consulta as $nome => $valor)@foreach((array) $valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach @endforeach
                    <input type="hidden" name="arquivada" value="{{ $emArquivadas ? '0' : '1' }}">
                    <button type="submit" class="botao-arquivamento" x-bind:disabled="enviando" x-on:click.stop aria-label="{{ $emArquivadas ? 'Desarquivar' : 'Arquivar' }} nota {{ $identificacao }}"><x-icone :nome="$emArquivadas ? 'desarquivar' : 'arquivo'" /><span>{{ $emArquivadas ? 'Desarquivar' : 'Arquivar' }}</span></button>
                </form>
                <form method="POST" action="{{ route('notas.mover-lixeira', $nota) }}" x-data="{ enviando: false }" x-on:submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
                    @csrf @method('DELETE')
                    @foreach($consulta as $nome => $valor)@foreach((array) $valor as $item)<input type="hidden" name="{{ is_array($valor) ? $nome.'[]' : $nome }}" value="{{ $item }}">@endforeach @endforeach
                    <button type="submit" class="botao-lixeira" x-bind:disabled="enviando" x-on:click.stop aria-label="Mover nota {{ $identificacao }} para a lixeira"><x-icone nome="lixeira" /><span>Lixeira</span></button>
                </form>
            @else
                <span class="acao-somente-leitura">{{ $papel }}</span>
            @endif
        </div>

        <details class="mais-acoes-nota">
            <summary>Mais ações</summary>
            <div class="painel-acoes-nota">
                <button type="button" x-on:click="copiarNota(@js(route('notas.exportar', ['nota' => $nota->id, 'texto' => 1])), @js(route('notas.exportar', $nota->id)))">Copiar como texto</button>
                <a href="{{ route('notas.exportar', $nota->id) }}">Baixar .txt</a>

                @unless($emLixeira)
                    <form method="POST" action="{{ route('notas.etiquetas', $nota) }}">
                        @csrf @method('PUT')
                        <fieldset><legend>Etiquetas pessoais</legend>
                            @forelse($etiquetas as $etiqueta)
                                <label><input type="checkbox" name="etiquetas[]" value="{{ $etiqueta->id }}" @checked(in_array($etiqueta->id, $etiquetasAtuais))> {{ $etiqueta->nome }}</label>
                            @empty
                                <span>Crie uma etiqueta nos filtros.</span>
                            @endforelse
                        </fieldset>
                        <button type="submit">Salvar etiquetas</button>
                    </form>
                    <form method="POST" action="{{ route('notas.lembrete', $nota) }}">
                        @csrf
                        <label>Lembrar em <input type="datetime-local" name="agendado_local" required></label>
                        <input type="hidden" name="fuso_horario" value="{{ auth()->user()->fuso_horario }}">
                        <label><input type="checkbox" name="enviar_email" value="1"> Enviar também por e-mail</label>
                        <button type="submit">Agendar lembrete</button>
                        <small>Fuso: {{ auth()->user()->fuso_horario }}</small>
                    </form>
                    @if($proprietaria)<a href="{{ route('compartilhamentos.index', ['nota' => $nota->id]) }}">Gerenciar compartilhamento</a>@endif
                @endunless
            </div>
        </details>
    </footer>
</article>
