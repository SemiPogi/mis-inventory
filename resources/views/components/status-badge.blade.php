@props(['status' => 'neutral'])

@php
    $map = [
        'pending'      => ['bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'label' => 'Pending'],
        'acknowledged' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'label' => 'Acknowledged'],
        'received'     => ['bg' => 'bg-primary-50', 'text' => 'text-primary-700', 'label' => 'Received'],
        'released'     => ['bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'label' => 'Released'],
        'neutral'      => ['bg' => 'bg-surface-page', 'text' => 'text-ink-body',  'label' => ''],
    ];
    $s = $map[$status] ?? $map['neutral'];
@endphp

<span {{ $attributes->class("inline-flex items-center {$s['bg']} {$s['text']} text-xs font-medium px-2.5 py-1 rounded-full") }}>
    {{ trim($slot) !== '' ? $slot : $s['label'] }}
</span>
