<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Reset password</h2>
    <p class="text-sm text-ink-muted mb-6">Choose a new password for your account.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="new-password"/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-label for="password_confirmation" required>Confirm password</x-label>
            <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"/>
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Reset password</x-button>
        </div>
    </form>
</x-guest-layout>
