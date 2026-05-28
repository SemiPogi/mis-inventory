@props([
    'variant' => 'primary',
    'type' => 'button',
    'as' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 text-sm font-medium px-5 py-2 rounded-lg transition active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-offset-2';
    $variants = [
        'primary' => 'bg-primary-600 hover:bg-primary-700 text-white focus:ring-primary-500',
        'ghost'   => 'border border-surface-border bg-white text-ink-body hover:bg-surface-page focus:ring-primary-500',
        'danger'  => 'bg-danger hover:bg-rose-700 text-white focus:ring-rose-500',
    ];
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if($as === 'a')
    <a {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
