@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'tituloId' => null,
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div
    x-data="{
        show: @js($show),
        retornoFoco: null,
        focusables() {
            const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'));
        },
        firstFocusable() { return this.focusables().find(el => el.hasAttribute('autofocus')) || this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % this.focusables().length },
        prevFocusableIndex() { return (this.focusables().indexOf(document.activeElement) - 1 + this.focusables().length) % this.focusables().length },
    }"
    x-init="$watch('show', value => {
        document.body.classList.toggle('overflow-y-hidden', value);
        if (value) {
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable()?.focus(), 100)' : '' }}
        } else if (retornoFoco) {
            setTimeout(() => retornoFoco.focus(), 100);
        }
    })"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') { retornoFoco = document.activeElement; show = true }"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable()?.focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable()?.focus()"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false" x-transition.opacity>
        <div class="absolute inset-0 bg-gray-700 opacity-60"></div>
    </div>

    <div
        x-show="show"
        role="dialog"
        aria-modal="true"
        @if($tituloId) aria-labelledby="{{ $tituloId }}" @endif
        class="mb-6 w-full mx-auto bg-white rounded-lg overflow-hidden shadow-xl transform transition-all {{ $maxWidth }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>