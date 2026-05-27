<x-app-layout>
    <x-page-header title="Profile" subtitle="Manage your account information and password"/>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 max-w-5xl">
        <x-bento-card class="lg:col-span-2">
            @include('profile.partials.update-profile-information-form')
        </x-bento-card>

        <x-bento-card>
            @include('profile.partials.update-password-form')
        </x-bento-card>

        <x-bento-card class="lg:col-span-3 border border-danger/20 bg-rose-50/50">
            @include('profile.partials.delete-user-form')
        </x-bento-card>
    </div>
</x-app-layout>
