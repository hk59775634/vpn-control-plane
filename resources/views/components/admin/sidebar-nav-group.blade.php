@props([
    'label' => '',
])

@if($label)
    <p class="px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400">{{ $label }}</p>
@endif
{{ $slot }}
