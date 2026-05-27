<x-app-layout>
    <x-page-header title="New User" subtitle="Create a system account and assign an access role.">
        <x-slot name="actions">
            <x-button href="{{ route('users.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-label for="name">Full Name</x-label>
                    <x-input id="name" name="name" :value="old('name')" placeholder="e.g. Juan dela Cruz" required autofocus/>
                    @error('name')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="email">Email Address</x-label>
                    <x-input id="email" name="email" type="email" :value="old('email')" placeholder="user@lumc.gov.ph" required/>
                    @error('email')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="role">Role</x-label>
                    <x-select id="role" name="role" required>
                        <option value="">Select a role…</option>
                        <option value="staff"      @selected(old('role') === 'staff')>Staff — basic inventory operations + voucher creation</option>
                        <option value="accounting" @selected(old('role') === 'accounting')>Accounting — voucher settlement + reports</option>
                        <option value="admin"      @selected(old('role') === 'admin')>Admin — full access</option>
                    </x-select>
                    @error('role')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="password">Password</x-label>
                    <x-input id="password" name="password" type="password" placeholder="Minimum 8 characters" required/>
                    @error('password')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="password_confirmation">Confirm Password</x-label>
                    <x-input id="password_confirmation" name="password_confirmation" type="password" required/>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('users.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-user-plus class="w-4 h-4"/>
                        Create User
                    </x-button>
                </div>

            </form>
        </x-bento-card>
    </div>
</x-app-layout>
