@props([
    'data' => [],
    'color' => '#0d9488',
    'height' => 40,
    'width' => 120,
])

@php
    $values = array_values(array_map('floatval', $data ?: [0]));
    $max = max($values) ?: 1;
    $min = min($values);
    $range = ($max - $min) ?: 1;
    $count = count($values);
    $stepX = $count > 1 ? $width / ($count - 1) : 0;

    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $stepX, 2);
        $y = round($height - (($v - $min) / $range) * $height, 2);
        $points[] = "{$x},{$y}";
    }
    $path = 'M ' . implode(' L ', $points);
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}"
     preserveAspectRatio="none"
     x-data x-intersect.once="$el.querySelector('path').classList.add('animate-chart-draw')"
     {{ $attributes->class('block w-full h-full') }}>
    <path d="{{ $path }}"
          fill="none"
          stroke="{{ $color }}"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          style="--dash-len: 600; stroke-dasharray: 600; stroke-dashoffset: 600;"/>
</svg>
