@props([
    'paginator' => null,
])

@php
    $hasPaginator = $paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages();
@endphp

@if($hasPaginator)
    <div {{ $attributes->merge(['class' => 'console-pagination']) }}>
        @if($hasPaginator)
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <span>第 {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} 页</span>
                <span class="text-slate-400">共 {{ $paginator->total() }} 条</span>
            </div>
            <div class="flex flex-wrap items-center gap-1">
                @if($paginator->onFirstPage())
                    <span class="console-pagination-btn cursor-not-allowed" disabled>上一页</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="console-pagination-btn">上一页</a>
                @endif
                @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    <a href="{{ $url }}"
                       class="console-pagination-btn {{ $page === $paginator->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach
                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="console-pagination-btn">下一页</a>
                @else
                    <span class="console-pagination-btn cursor-not-allowed" disabled>下一页</span>
                @endif
            </div>
        @endif
    </div>
@endif
