<header class="barra-superior">
    <a href="{{ route('notas.inicio') }}" aria-label="Notas — página inicial"><x-marca /></a>
    <div class="conta">
        <span class="nome-usuario" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</span>
        <a class="link-perfil" href="{{ route('profile.edit') }}">Meu perfil</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="botao-sair" type="submit">Sair <span aria-hidden="true">↗</span></button></form>
    </div>
</header>
