@props([
    'store' => 'toast',
])

<div x-data
     x-show="$store.{{ $store }}.visible"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-x-4"
     x-transition:enter-end="opacity-100 translate-x-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-x-0"
     x-transition:leave-end="opacity-0 translate-x-4"
     class="console-toast fixed right-4 top-4 z-[100] w-full max-w-sm"
     :class="{
         'console-toast-success': $store.{{ $store }}.type === 'success',
         'console-toast-error': $store.{{ $store }}.type === 'error',
         'console-toast-info': $store.{{ $store }}.type === 'info'
     }"
     style="display: none;"
     role="status"
     aria-live="polite"
>
    <p class="font-medium" x-text="$store.{{ $store }}.title"></p>
    <p class="mt-0.5 text-sm opacity-90" x-text="$store.{{ $store }}.message"></p>
</div>
