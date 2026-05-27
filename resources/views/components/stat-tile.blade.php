@props([
    'label' => '',
    'value' => 0,
    'icon' => null,
    'variant' => 'default',
    'animate' => true,
])

@php
    $bg = $variant === 'hero'
        ? 'bg-gradient-to-br from-primary-600 to-accent-500 text-white'
        : 'bg-surface-tile';
    $labelColor = $variant === 'hero' ? 'text-white/80' : 'text-ink-muted';
    $valueColor = $variant === 'hero' ? 'text-white' : 'text-ink-heading';
    $iconColor  = $variant === 'hero' ? 'text-white/80' : 'text-primary-600';
@endphp

<div {{ $attributes->class("$bg rounded-2xl shadow-tile p-5 transition hover:-translate-y-1 hover:shadow-tile-hover") }}>
    <div class="flex items-start justify-between">
        <p class="text-xs {{ $labelColor }} uppercase tracking-wide font-medium">{{ $label }}</p>
        @if($icon)
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 {{ $iconColor }}"/>
        @endif
    </div>
    <p class="text-3xl font-bold {{ $valueColor }} mt-2" @if($animate) x-data x-count-up @endif>{{ $value }}</p>
    @if(isset($trend))
        <div class="mt-2 text-xs">{{ $trend }}</div>
    @endif
</div>
