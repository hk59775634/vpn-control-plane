@props([
    'brand' => 'VPN SaaS · 控制面',
    'searchPlaceholder' => '搜索资源...',
    'showSearch' => true,
])

<header {{ $attributes->merge(['class' => 'console-nav fixed left-0 right-0 top-0 z-50']) }}>
    <div class="flex w-full items-center justify-between gap-4 px-4">
        <span class="console-nav-brand shrink-0">{{ $brand }}</span>
        @if($showSearch)
            <div class="min-w-0 flex-1 max-w-md">
                <input type="search"
                       placeholder="{{ $searchPlaceholder }}"
                       class="w-full rounded-md border border-slate-600 bg-slate-800 py-1.5 pl-3 pr-4 text-sm text-white placeholder-slate-400 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                       x-ref="topbarSearch"
                       @keydown.escape.window="$refs.topbarSearch?.blur()"
                >
            </div>
        @endif
        <div class="flex shrink-0 items-center gap-2">
            {{ $slot }}
        </div>
    </div>
</header>
