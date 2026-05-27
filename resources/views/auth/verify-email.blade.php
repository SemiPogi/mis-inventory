<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Verify your email</h2>
    <p class="text-sm text-ink-muted mb-6">
        We sent a verification link to your email. Click it to activate your account.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm bg-emerald-50 text-emerald-800 rounded-lg px-3 py-2">
            A new verification link has been sent.
        </div>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-ink-muted hover:text-primary-600">Log out</button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" variant="primary">Resend verification email</x-button>
        </form>
    </div>
</x-guest-layout>
