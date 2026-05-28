<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MIS Inventory') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-page text-ink-body antialiased">
    <div class="min-h-screen flex">
        {{-- Left: brand panel --}}
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary-600 to-accent-500 text-white p-12 flex-col justify-between">
            <div>
                <x-application-logo class="w-12 h-12 fill-current text-white"/>
                <p class="mt-6 text-sm uppercase tracking-widest opacity-80">La Union Medical Center</p>
                <h1 class="mt-2 text-3xl font-bold">MIS Office Inventory</h1>
                <p class="mt-4 text-white/80 max-w-md text-sm leading-relaxed">
                    Track items received, released, and acknowledged across hospital offices — from a single, unified workspace.
                </p>
            </div>
            <p class="text-xs opacity-70">© {{ date('Y') }} La Union Medical Center</p>
        </div>

        {{-- Right: form panel --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6">
            <div class="w-full max-w-md animate-slide-up">
                <div class="lg:hidden mb-6 flex justify-center">
                    <x-application-logo class="w-12 h-12 fill-current text-primary-600"/>
                </div>
                <x-bento-card class="p-8">
                    {{ $slot }}
                </x-bento-card>
            </div>
        </div>
    </div>

    <x-toast-container/>
</body>
</html>
