@props([
    'type' => 'success',
])

@php
    $map = [
        'success' => ['icon' => 'check-circle', 'bg' => 'bg-emerald-50',  'text' => 'text-emerald-800', 'iconColor' => 'text-emerald-600'],
        'error'   => ['icon' => 'x-circle',     'bg' => 'bg-rose-50',     'text' => 'text-rose-800',    'iconColor' => 'text-rose-600'],
        'info'    => ['icon' => 'information-circle', 'bg' => 'bg-primary-50', 'text' => 'text-primary-800', 'iconColor' => 'text-primary-600'],
    ];
    $t = $map[$type] ?? $map['success'];
@endphp

<div x-data="{ shown: true }"
     x-show="shown"
     x-init="setTimeout(() => shown = false, 4000)"
     x-transition:enter="animate-slide-in-right"
     x-transition:leave="animate-fade-out"
     class="flex items-start gap-3 {{ $t['bg'] }} {{ $t['text'] }} rounded-xl shadow-tile px-4 py-3 max-w-sm pointer-events-auto">
    <x-dynamic-component :component="'heroicon-o-' . $t['icon']" class="w-5 h-5 {{ $t['iconColor'] }} shrink-0 mt-0.5"/>
    <div class="text-sm flex-1">{{ $slot }}</div>
    <button @click="shown = false" class="{{ $t['iconColor'] }} hover:opacity-70">
        <x-heroicon-o-x-mark class="w-4 h-4"/>
    </button>
</div>
