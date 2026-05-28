@props(['for' => null, 'required' => false])

<label @if($for) for="{{ $for }}" @endif
    class="block text-xs font-medium text-ink-muted uppercase tracking-wide mb-1.5">
    {{ $slot }}@if($required)<span class="text-danger ml-0.5">*</span>@endif
</label>
