<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Welcome back</h2>
    <p class="text-sm text-ink-muted mb-6">Sign in to continue.</p>

    <x-auth-session-status class="mb-4" :status="session('status')"/>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username"/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="current-password"/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <label for="remember_me" class="inline-flex items-center text-sm text-ink-body">
            <input id="remember_me" type="checkbox" class="rounded border-surface-border text-primary-600 focus:ring-primary-500" name="remember">
            <span class="ms-2">Remember me</span>
        </label>

        <div class="flex items-center justify-between pt-2">
            @if (Route::has('password.request'))
                <a class="text-xs text-ink-muted hover:text-primary-600" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
            <x-button type="submit" variant="primary">Log in</x-button>
        </div>
    </form>
</x-guest-layout>
