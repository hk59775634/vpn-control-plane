@props([
    'title' => '编辑',
    'closeVar' => null,
])

<div class="console-drawer-header">
    <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
    <button type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            @click="{{ $closeVar ? $closeVar . ' = false' : "\$dispatch('drawer-close')" }}"
            aria-label="关闭">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
