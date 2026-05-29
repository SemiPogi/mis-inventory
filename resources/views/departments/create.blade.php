<x-app-layout>
    <x-page-header title="New Department" subtitle="Add a department to the hospital-wide system.">
        <x-slot name="actions">
            <x-button href="{{ route('departments.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('departments.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-label for="name">Department Name</x-label>
                    <x-input id="name" name="name" :value="old('name')" placeholder="e.g. Nursing Department" required autofocus/>
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="code">Department Code</x-label>
                    <x-input id="code" name="code" :value="old('code')" placeholder="e.g. NURS" required/>
                    <p class="mt-1 text-xs text-ink-muted">Short uppercase code used on RIS forms.</p>
                    @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="responsibility_center_code">Responsibility Center Code</x-label>
                    <x-input id="responsibility_center_code" name="responsibility_center_code" :value="old('responsibility_center_code')" placeholder="Optional"/>
                    @error('responsibility_center_code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <input type="checkbox" id="is_supply_hub" name="is_supply_hub" value="1"
                           class="rounded border-surface-border text-primary-600"
                           {{ old('is_supply_hub') ? 'checked' : '' }}>
                    <label for="is_supply_hub" class="text-sm text-ink-body">
                        This is the <strong>Supply Hub</strong> (only one allowed hospital-wide)
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('departments.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-building-office class="w-4 h-4"/>
                        Create Department
                    </x-button>
                </div>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
