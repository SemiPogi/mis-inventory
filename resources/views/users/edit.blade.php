<x-app-layout>
    <x-page-header title="Edit User" :subtitle="'Editing account for ' . $user->name">
        <x-slot name="actions">
            <x-button href="{{ route('users.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
                @csrf @method('PATCH')

                <div>
                    <x-label for="name">Full Name</x-label>
                    <x-input id="name" name="name" :value="old('name', $user->name)" required autofocus/>
                    @error('name')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="email">Email Address</x-label>
                    <x-input id="email" name="email" type="email" :value="old('email', $user->email)" required/>
                    @error('email')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-label for="role">Role</x-label>
                    <x-select id="role" name="role" required>
                        <option value="staff"      @selected(old('role', $user->role) === 'staff')>Staff — basic inventory operations + voucher creation</option>
                        <option value="accounting" @selected(old('role', $user->role) === 'accounting')>Accounting — voucher settlement + reports</option>
                        <option value="admin"      @selected(old('role', $user->role) === 'admin')>Admin — full access</option>
                    </x-select>
                    @error('role')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-surface-border pt-4">
                    <p class="text-xs text-ink-muted mb-3">Leave password fields blank to keep the current password.</p>

                    <div class="space-y-4">
                        <div>
                            <x-label for="password">New Password</x-label>
                            <x-input id="password" name="password" type="password" placeholder="Minimum 8 characters"/>
                            @error('password')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-label for="password_confirmation">Confirm New Password</x-label>
                            <x-input id="password_confirmation" name="password_confirmation" type="password"/>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('users.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-check class="w-4 h-4"/>
                        Save Changes
                    </x-button>
                </div>

            </form>
        </x-bento-card>
    </div>
</x-app-layout>
