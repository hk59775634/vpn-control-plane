@props([
    'store' => 'confirm',
])

<div x-data
     x-show="$store.{{ $store }}.visible"
     x-cloak
     class="console-modal-backdrop"
     style="display: none;"
     aria-modal="true"
     role="alertdialog"
     @keydown.escape.window="$store.{{ $store }}.cancel()"
>
    <div class="console-modal-box"
         @click.stop
         x-show="$store.{{ $store }}.visible"
         x-transition
    >
        <h3 class="text-base font-semibold text-slate-900" x-text="$store.{{ $store }}.title"></h3>
        <p class="mt-2 text-sm text-slate-600" x-text="$store.{{ $store }}.message"></p>
        <div class="console-modal-footer">
            <button type="button"
                    class="console-btn-secondary"
                    @click="$store.{{ $store }}.cancel()"
                    x-text="$store.{{ $store }}.cancelLabel"
            ></button>
            <button type="button"
                    :class="$store.{{ $store }}.destructive ? 'console-btn-danger' : 'console-btn-primary'"
                    @click="$store.{{ $store }}.confirm()"
                    x-text="$store.{{ $store }}.confirmLabel"
            ></button>
        </div>
    </div>
</div>
