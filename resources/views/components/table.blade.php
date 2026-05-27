@props(['headers' => []])

<div {{ $attributes->class('overflow-x-auto rounded-2xl bg-surface-tile shadow-tile') }}>
    <table class="w-full text-sm">
        @if(count($headers) > 0)
        <thead class="bg-surface-page/50">
            <tr class="border-b border-surface-border">
                @foreach($headers as $h)
                    <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
