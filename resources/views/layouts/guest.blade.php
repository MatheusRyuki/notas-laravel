<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo ?? 'Sua conta' }} · Notas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased pagina-acesso">
    <header class="cabecalho-acesso"><a href="{{ route('login') }}" aria-label="Notas — entrar"><x-marca /></a><span>Um lugar para suas ideias</span></header>
    <main class="acesso">
        <aside class="apresentacao">
            <span class="etiqueta">MENOS DISTRAÇÃO, MAIS ESPAÇO</span>
            <h1>Grandes ideias.<br>Pequenas notas.</h1>
            <p>Um espaço simples para guardar o que importa.<br>Comece criando o seu cantinho.</p>
            <div class="ilustracao" aria-hidden="true">
                <div class="papel papel-fundo"></div>
                <div class="papel papel-frente"><span>Deixe espaço<br>para uma boa ideia.</span><svg viewBox="0 0 120 35" fill="none"><path d="M5 21C25 4 45 34 62 15S90 8 113 13" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><small>uma coisa de cada vez ☀</small></div>
            </div>
            <span class="rodape-apresentacao">Seu espaço. Seu ritmo.</span>
        </aside>
        <section class="cartao-acesso" aria-labelledby="titulo-acesso">
            <span class="etiqueta">BEM-VINDO AO NOTAS</span>
            <h2 id="titulo-acesso">{{ $titulo ?? 'Sua conta' }}</h2>
            <p class="subtitulo-acesso">{{ $subtitulo ?? 'Cuide do acesso ao seu espaço.' }}</p>
            {{ $slot }}
        </section>
    </main>
    <footer class="rodape-acesso">Projeto de estudo · Primeiros passos</footer>
</body>
</html>
