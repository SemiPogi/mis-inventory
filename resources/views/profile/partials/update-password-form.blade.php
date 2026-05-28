<header class="mb-4">
    <h2 class="text-base font-semibold text-ink-heading">Update Password</h2>
    <p class="text-xs text-ink-muted mt-1">Use a strong password.</p>
</header>

<form method="post" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    @method('put')

    <div>
        <x-label for="current_password" required>Current</x-label>
        <x-input id="current_password" type="password" name="current_password" autocomplete="current-password"/>
        @error('updatePassword.current_password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="password" required>New</x-label>
        <x-input id="password" type="password" name="password" autocomplete="new-password"/>
        @error('updatePassword.password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="password_confirmation" required>Confirm</x-label>
        <x-input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"/>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <x-button type="submit" variant="primary">Save</x-button>
        @if (session('status') === 'password-updated')
            <p class="text-xs text-emerald-700">Saved.</p>
        @endif
    </div>
</form>
