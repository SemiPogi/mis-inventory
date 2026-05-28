@props([
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'hint' => null,
])

<div class="flex flex-col items-center justify-center text-center py-12 px-6">
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-10 h-10 text-ink-muted mb-3"/>
    <p class="text-sm font-medium text-ink-heading">{{ $title }}</p>
    @if($hint)
        <p class="text-xs text-ink-muted mt-1">{{ $hint }}</p>
    @endif
    @if(isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
