<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MIS Inventory — LUMC</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans">

<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
        <div class="px-6 py-5 border-b border-gray-200">
            <p class="text-sm font-semibold text-blue-700 uppercase tracking-wide">MIS Office</p>
            <p class="text-xs text-gray-400 mt-1">La Union Medical Center</p>
        </div>

        <nav class="flex-1 px-4 py-4 space-y-1">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('/') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Dashboard
            </a>
            <a href="{{ route('receive.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('receive') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Receive Item
            </a>
            <a href="{{ route('release.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('release') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Release Item
            </a>
            <a href="{{ route('acknowledge.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('acknowledge') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Acknowledge
            </a>
            <a href="{{ route('transactions.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('transactions*') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Transactions
            </a>
            <a href="{{ route('items.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->is('items*') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                Inventory
            </a>
        </nav>

        <div class="px-6 py-4 border-t border-gray-200">
            <p class="text-sm font-medium text-gray-700">{{ auth()->user()->name }}</p>
            <p class="text-xs text-gray-400">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Logout</button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 p-8 overflow-auto">
        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

</div>

</body>
</html>