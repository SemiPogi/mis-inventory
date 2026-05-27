<header class="mb-4">
    <h2 class="text-base font-semibold text-ink-heading">Profile Information</h2>
    <p class="text-xs text-ink-muted mt-1">Update your name and email address.</p>
</header>

<form method="post" action="{{ route('profile.update') }}" class="space-y-4">
    @csrf
    @method('patch')

    <div>
        <x-label for="name" required>Name</x-label>
        <x-input id="name" name="name" :value="old('name', auth()->user()->name)" required autofocus autocomplete="name"/>
        @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="email" required>Email</x-label>
        <x-input id="email" type="email" name="email" :value="old('email', auth()->user()->email)" required autocomplete="username"/>
        @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <p class="mt-2 text-xs text-ink-muted">
                Your email address is unverified.
                <button form="send-verification" class="underline text-primary-600 hover:text-primary-700">Resend verification email</button>
            </p>
            @if (session('status') === 'verification-link-sent')
                <p class="mt-2 text-xs text-emerald-700">A new verification link has been sent.</p>
            @endif
        @endif
    </div>

    <div class="flex items-center gap-3 pt-2">
        <x-button type="submit" variant="primary">Save</x-button>
        @if (session('status') === 'profile-updated')
            <p class="text-xs text-emerald-700">Saved.</p>
        @endif
    </div>
</form>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
