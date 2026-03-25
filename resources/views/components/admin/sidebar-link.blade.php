@props([
    'active' => false,
    'href' => '#',
])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'console-sidebar-link block'])->when($active, fn($c) => $c->merge(['class' => 'console-sidebar-link active block'])) }}
>
    {{ $slot }}
</a>
