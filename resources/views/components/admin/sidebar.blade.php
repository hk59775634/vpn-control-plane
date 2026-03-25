@props([
    'brand' => '管理后台',
    'subtitle' => '控制台',
    'brandUrl' => null,
])

<aside {{ $attributes->merge(['class' => 'console-sidebar']) }}>
    <div class="console-sidebar-header">
        @if($brandUrl)
            <a href="{{ $brandUrl }}" class="console-nav-brand block text-white">{{ $brand }}</a>
        @else
            <span class="console-nav-brand block text-white">{{ $brand }}</span>
        @endif
        @if($subtitle)
            <p class="mt-0.5 text-xs text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>
    <nav class="console-sidebar-nav">
        {{ $slot }}
    </nav>
    @isset($footer)
        @if($footer->isNotEmpty())
            <div class="border-t border-slate-700/50 p-3">
                {{ $footer }}
            </div>
        @endif
    @endisset
</aside>
