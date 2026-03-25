@props([
    'headers' => [],
    'hasActions' => true,
    'emptyMessage' => '暂无数据',
])

<div {{ $attributes->merge(['class' => 'console-table-wrap']) }}>
    @if(isset($toolbar))
        <div class="console-filter-bar">
            {{ $toolbar }}
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="console-table">
            <thead>
                <tr>
                    @foreach($headers as $h)
                        <th>{{ is_array($h) ? ($h['label'] ?? $h['key'] ?? '') : $h }}</th>
                    @endforeach
                    @if($hasActions)
                        <th class="w-32">操作</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @if(isset($empty))
        {{ $empty }}
    @else
        <p class="py-6 text-center text-sm text-slate-500" style="display: none;" x-show="typeof emptyRows !== 'undefined' ? emptyRows : false">{{ $emptyMessage }}</p>
    @endif
</div>
