<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Forgot your password?</h2>
    <p class="text-sm text-ink-muted mb-6">Enter your email and we'll send a reset link.</p>

    <x-auth-session-status class="mb-4" :status="session('status')"/>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Email password reset link</x-button>
        </div>
    </form>
</x-guest-layout>
