@props(['type' => 'text'])

<input type="{{ $type }}"
    {{ $attributes->class('w-full border border-surface-border bg-white rounded-lg px-3 py-2 text-sm text-ink-heading placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition') }}>
