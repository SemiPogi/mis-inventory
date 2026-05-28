<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Confirm password</h2>
    <p class="text-sm text-ink-muted mb-6">Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="current-password" autofocus/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Confirm</x-button>
        </div>
    </form>
</x-guest-layout>
