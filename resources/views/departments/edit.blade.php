<x-app-layout>
    <x-page-header title="Edit Department" :subtitle="'Editing ' . $department->name">
        <x-slot name="actions">
            <x-button href="{{ route('departments.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('departments.update', $department) }}" class="space-y-5">
                @csrf @method('PATCH')

                <div>
                    <x-label for="name">Department Name</x-label>
                    <x-input id="name" name="name" :value="old('name', $department->name)" required autofocus/>
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="code">Department Code</x-label>
                    <x-input id="code" name="code" :value="old('code', $department->code)" required/>
                    @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="responsibility_center_code">Responsibility Center Code</x-label>
                    <x-input id="responsibility_center_code" name="responsibility_center_code" :value="old('responsibility_center_code', $department->responsibility_center_code)"/>
                    @error('responsibility_center_code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <input type="checkbox" id="is_supply_hub" name="is_supply_hub" value="1"
                           class="rounded border-surface-border text-primary-600"
                           {{ old('is_supply_hub', $department->is_supply_hub) ? 'checked' : '' }}>
                    <label for="is_supply_hub" class="text-sm text-ink-body">
                        This is the <strong>Supply Hub</strong>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('departments.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-check class="w-4 h-4"/>
                        Save Changes
                    </x-button>
                </div>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
