@props([
    'searchPlaceholder' => '搜索...',
    'searchModel' => 'filterQuery',
])

<div {{ $attributes->merge(['class' => 'console-filter-bar']) }}>
    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
        <input type="search"
               placeholder="{{ $searchPlaceholder }}"
               class="console-filter-input max-w-xs"
               x-model="{{ $searchModel }}"
               @isset($searchRef) x-ref="{{ $searchRef }}" @endisset
        >
        @if(isset($filters))
            {{ $filters }}
        @endif
    </div>
    @if(isset($actions))
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
