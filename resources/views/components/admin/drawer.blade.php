@props([
    'showVar' => 'drawerOpen',
    'width' => 'max-w-lg',
])

{{-- 由父级 x-data 提供 showVar（如 drawerOpen），父级设置 drawerOpen = true 即可打开 --}}
<div x-show="{{ $showVar }}"
     x-cloak
     class="fixed inset-0 z-40"
     style="display: none;"
     aria-modal="true"
     role="dialog"
>
    <div class="console-drawer-backdrop"
         x-show="{{ $showVar }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="{{ $showVar }} = false"
    ></div>
    <div class="console-drawer {{ $width }} absolute inset-y-0 right-0"
         x-show="{{ $showVar }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
    >
        @if(isset($header))
            <div class="console-drawer-header">
                {{ $header }}
            </div>
        @endif
        <div class="console-drawer-body">
            {{ $slot }}
        </div>
        @if(isset($footer))
            <div class="console-drawer-footer">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
