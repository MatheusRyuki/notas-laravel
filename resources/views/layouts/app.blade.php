<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#354e3f">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ request()->routeIs('lixeira.*') ? 'Lixeira' : (request()->routeIs('notas.arquivadas') ? 'Arquivadas' : (request()->routeIs('lembretes.*') ? 'Lembretes' : (request()->routeIs('compartilhamentos.*') ? 'Compartilhamentos' : (request()->routeIs('notas.*') ? 'Minhas notas' : 'Meu perfil')))) }} · Notas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    @php($consultaNavegacao = collect(request()->only(['q','ordem','etiquetas']))->reject(fn($v) => $v === null || $v === '' || $v === [])->all())
    <a class="pular-conteudo" href="#conteudo">Pular para o conteúdo</a>
    @include('layouts.navigation')
    <div id="estado-offline" class="estado-offline" role="status" aria-live="polite" hidden></div>
    <div class="estrutura">
        <aside class="barra-lateral">
            <nav aria-label="Navegação principal">
                <a href="{{ route('notas.inicio', $consultaNavegacao) }}" class="{{ request()->routeIs('notas.inicio') ? 'selecionado' : '' }}" @if(request()->routeIs('notas.inicio')) aria-current="page" @endif><x-icone nome="notas" /> Minhas notas</a>
                <a href="{{ route('notas.arquivadas', $consultaNavegacao) }}" class="{{ request()->routeIs('notas.arquivadas') ? 'selecionado' : '' }}" @if(request()->routeIs('notas.arquivadas')) aria-current="page" @endif><x-icone nome="arquivo" /> Arquivadas</a>
                <a href="{{ route('lembretes.index') }}" class="{{ request()->routeIs('lembretes.*') ? 'selecionado' : '' }}" @if(request()->routeIs('lembretes.*')) aria-current="page" @endif><x-icone nome="lembrete" /> Lembretes</a>
                <a href="{{ route('compartilhamentos.index') }}" class="{{ request()->routeIs('compartilhamentos.*') ? 'selecionado' : '' }}" @if(request()->routeIs('compartilhamentos.*')) aria-current="page" @endif><x-icone nome="compartilhar" /> Compartilhamentos</a>
                <a href="{{ route('lixeira.index', $consultaNavegacao) }}" class="{{ request()->routeIs('lixeira.*') ? 'selecionado' : '' }}" @if(request()->routeIs('lixeira.*')) aria-current="page" @endif><x-icone nome="lixeira" /> Lixeira</a>
            </nav>
        </aside>
        <main id="conteudo" class="conteudo">
            @isset($header)<header class="cabecalho-pagina">{{ $header }}</header>@endisset
            {{ $slot }}
        </main>
    </div>
</body>
</html>
