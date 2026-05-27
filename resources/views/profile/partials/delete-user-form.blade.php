<header class="mb-4">
    <h2 class="text-base font-semibold text-danger">Delete Account</h2>
    <p class="text-xs text-ink-body mt-1">
        Once deleted, all resources and data will be permanently removed. Download anything you want to keep first.
    </p>
</header>

<div x-data="{ open: false }">
    <x-button type="button" variant="danger" @click="open = true">Delete account</x-button>

    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 w-full max-w-md mx-4 animate-pop">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <h3 class="text-base font-semibold text-ink-heading mb-2">Are you sure?</h3>
                <p class="text-sm text-ink-body mb-4">
                    This will permanently delete your account. Enter your password to confirm.
                </p>

                <x-label for="password" required>Password</x-label>
                <x-input id="password" type="password" name="password" required/>
                @error('userDeletion.password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-2 mt-5">
                    <x-button type="button" variant="ghost" @click="open = false">Cancel</x-button>
                    <x-button type="submit" variant="danger">Delete account</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
