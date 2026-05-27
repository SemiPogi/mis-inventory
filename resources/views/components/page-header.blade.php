@props(['title', 'subtitle' => null])

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-ink-heading">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-ink-muted mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex gap-2">{{ $actions }}</div>
    @endif
</div>
