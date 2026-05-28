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
<body class="bg-surface-page text-ink-body antialiased">

@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard',          'label' => 'Dashboard',    'icon' => 'home',                    'match' => '/'],
        ['route' => 'receive.index',      'label' => 'Receive',      'icon' => 'arrow-down-tray',         'match' => 'receive'],
        ['route' => 'release.index',      'label' => 'Release',      'icon' => 'arrow-up-tray',           'match' => 'release'],
        ['route' => 'acknowledge.index',  'label' => 'Acknowledge',  'icon' => 'check-circle',            'match' => 'acknowledge'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'clipboard-document-list', 'match' => 'transactions*'],
        ['route' => 'items.index',        'label' => 'Inventory',    'icon' => 'cube',                    'match' => 'items*'],
    ];

    $pcBadge = 0;
    if ($user->canCreateVoucher()) {
        $pcBadge = \App\Models\PettyCashVoucher::where('status', 'submitted')->count();
    } elseif ($user->canSettleVoucher()) {
        $pcBadge = \App\Models\PettyCashVoucher::where('status', 'acknowledged')->count();
    }
@endphp

<div class="flex min-h-screen" x-data="{ collapsed: localStorage.getItem('sidebar-collapsed') === '1' }">

    {{-- Sidebar --}}
    <aside :class="collapsed ? 'w-20' : 'w-64'"
           class="bg-surface-tile border-r border-surface-border flex flex-col transition-all duration-200">

        <div class="px-5 py-5 border-b border-surface-border flex items-center justify-between">
            <div x-show="!collapsed" x-transition.opacity>
                <p class="text-sm font-semibold text-primary-700 uppercase tracking-wide">MIS Office</p>
                <p class="text-xs text-ink-muted mt-0.5">La Union Medical Center</p>
            </div>
            <button @click="collapsed = !collapsed; localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0')"
                    class="text-ink-muted hover:text-primary-600 transition" title="Toggle sidebar">
                <x-heroicon-o-bars-3 class="w-5 h-5"/>
            </button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1">
            @foreach($nav as $item)
                @php $active = request()->is($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $active
                                ? 'bg-primary-50 text-primary-700 font-medium'
                                : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($active)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>{{ $item['label'] }}</span>
                </a>
            @endforeach

            {{-- Petty Cash (all roles) --}}
            @php $pcActive = request()->is('petty-cash*'); @endphp
            <a href="{{ route('petty-cash.index') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                      {{ $pcActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                @if($pcActive)
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                @endif
                <span class="relative shrink-0">
                    <x-heroicon-o-banknotes class="w-5 h-5"/>
                    @if($pcBadge > 0)
                        <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                            {{ $pcBadge > 9 ? '9+' : $pcBadge }}
                        </span>
                    @endif
                </span>
                <span x-show="!collapsed" x-transition.opacity>Petty Cash</span>
            </a>

            {{-- Reports (accounting + admin) --}}
            @if($user->canAccessReports())
                @php $repActive = request()->is('reports*'); @endphp
                <a href="{{ route('reports.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $repActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($repActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>Reports</span>
                </a>
            @endif

            {{-- Users (admin only) --}}
            @if($user->canManageUsers())
                @php $usersActive = request()->is('users*'); @endphp
                <a href="{{ route('users.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $usersActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($usersActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-heroicon-o-users class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>Users</span>
                </a>
            @endif
        </nav>

        <div class="px-5 py-4 border-t border-surface-border" x-show="!collapsed" x-transition.opacity>
            <p class="text-sm font-medium text-ink-heading truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-ink-muted truncate">{{ auth()->user()->email }}</p>
            <div class="mt-2 flex gap-3 text-xs">
                <a href="{{ route('profile.edit') }}" class="text-ink-muted hover:text-primary-600">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-danger hover:text-rose-700">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <main class="flex-1 p-8 overflow-auto">
        {{ $slot }}
    </main>
</div>

<x-toast-container />

</body>
</html>
