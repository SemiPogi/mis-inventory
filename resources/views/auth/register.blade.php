<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Create an account</h2>
    <p class="text-sm text-ink-muted mb-6">Register a new MIS Office user.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="name" required>Name</x-label>
            <x-input id="name" name="name" :value="old('name')" required autofocus autocomplete="name"/>
            @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username"/>
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

        <div class="flex items-center justify-between pt-2">
            <a class="text-xs text-ink-muted hover:text-primary-600" href="{{ route('login') }}">Already registered?</a>
            <x-button type="submit" variant="primary">Register</x-button>
        </div>
    </form>
</x-guest-layout>
