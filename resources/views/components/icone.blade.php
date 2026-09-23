@props(['nome', 'class' => 'icone'])

<svg
    {{ $attributes->merge(['class' => $class]) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @switch($nome)
        @case('notas')
            <path d="M6 4.5h12a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V6A1.5 1.5 0 0 1 6 4.5Z" />
            <path d="M8 9h8M8 13h8M8 17h5" />
            @break
        @case('arquivo')
            <path d="M4 7.5h16v11A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-11Z" />
            <path d="M3.5 4h17v3.5h-17zM9 11.5h6" />
            @break
        @case('desarquivar')
            <path d="M4 7.5h16v11A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-11Z" />
            <path d="M3.5 4h17v3.5h-17zM12 16v-5M9.5 13.5 12 11l2.5 2.5" />
            @break
        @case('lixeira')
            <path d="M5.5 7h13M9 7V4.5h6V7M7.5 7l.7 12h7.6l.7-12M10 10.5v5M14 10.5v5" />
            @break
        @case('mais')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('busca')
            <circle cx="10.5" cy="10.5" r="5.5" />
            <path d="m15 15 4.5 4.5" />
            @break
        @case('fixada')
            <path d="m8 4 8 8M14.5 3.5l6 6-3 1.5-4.5 4.5-1.5 3-6-6 3-1.5L13 6.5l1.5-3Z" fill="currentColor" stroke="none" />
            <path d="m9 15-5 5" />
            @break
        @case('fixar')
            <path d="m8 4 8 8M14.5 3.5l6 6-3 1.5-4.5 4.5-1.5 3-6-6 3-1.5L13 6.5l1.5-3Z" />
            <path d="m9 15-5 5" />
            @break
        @case('restaurar')
            <path d="M4.5 8.5V4.5h4" />
            <path d="M5.2 7a8 8 0 1 1-1 8" />
            @break
        @case('excluir')
            <path d="m7 7 10 10M17 7 7 17" />
            @break
        @case('fechar')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @case('sair')
            <path d="M10 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19H10M13 8l4 4-4 4M9 12h8" />
            @break
        @case('seta-direita')
            <path d="M5 12h14M14 7l5 5-5 5" />
            @break
        @case('mover-cima')
            <path d="M12 19V5M7 10l5-5 5 5" />
            @break
        @case('mover-baixo')
            <path d="M12 5v14M7 14l5 5 5-5" />
            @break
        @case('check')
            <path d="m6 12 4 4 8-8" />
            @break
        @case('checkbox-marcado')
            <rect x="4.5" y="4.5" width="15" height="15" rx="3" />
            <path d="m8 12 2.6 2.6L16.5 9" />
            @break
        @case('checkbox-vazio')
            <rect x="4.5" y="4.5" width="15" height="15" rx="3" />
            @break
        @case('lembrete')
            <path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 7H3s3 0 3-7M9.5 19a2.5 2.5 0 0 0 5 0" />
            @break
        @case('relogio')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
            @break
        @case('mais-acoes')
            <circle cx="5" cy="12" r="1.2" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none" />
            <circle cx="19" cy="12" r="1.2" fill="currentColor" stroke="none" />
            @break
        @case('etiqueta')
            <path d="M4.5 5.5v5.8L12.2 19l6.8-6.8-7.7-7.7H5.5a1 1 0 0 0-1 1Z" />
            <circle cx="8.2" cy="8.2" r="1.2" />
            @break
        @case('copiar')
            <rect x="8" y="8" width="11" height="11" rx="2" />
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
            @break
        @case('baixar')
            <path d="M12 4v11M8 11l4 4 4-4M5 20h14" />
            @break
        @case('compartilhar')
            <circle cx="8" cy="8" r="3" /><circle cx="17" cy="7" r="2.5" /><path d="M3.5 19c.5-4 2-6 4.5-6s4 2 4.5 6M13 14c1-.8 2-1.2 3.2-1 2.2.2 3.5 2.2 4 5" />
            @break
    @endswitch
</svg>
