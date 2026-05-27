@props([
    'variant' => 'default',
    'padded'  => true,
    'hover'   => false,
])

@php
    $base = 'rounded-2xl shadow-tile transition';
    $variants = [
        'default' => 'bg-surface-tile',
        'accent'  => 'bg-primary-50',
        'hero'    => 'bg-gradient-to-br from-primary-600 to-accent-500 text-white',
    ];
    $hoverClasses = $hover ? 'hover:-translate-y-1 hover:shadow-tile-hover' : '';
    $padding = $padded ? 'p-5' : '';
    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['default']) . ' ' . $padding . ' ' . $hoverClasses);
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
