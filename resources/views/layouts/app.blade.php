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

    // RIS: head queue badge — pending_head for this user's dept
    $risHeadBadge = 0;
    if ($user->is_head && $user->department_id) {
        $risHeadBadge = \App\Models\RisRequest::where('status', 'pending_head')
            ->where('requesting_dept_id', $user->department_id)
            ->count();
    } elseif ($user->isAdmin()) {
        $risHeadBadge = \App\Models\RisRequest::where('status', 'pending_head')->count();
    }

    // RIS: supply queue badge — pending_supply count
    $risSupplyBadge = 0;
    $supplyHub = \App\Models\Department::supplyHub();
    if ($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id)) {
        $risSupplyBadge = \App\Models\RisRequest::where('status', 'pending_supply')->count();
    }

    // Transfer head queue badge
    $transferHeadBadge = 0;
    if ($user->is_head && $user->department_id) {
        $transferHeadBadge = \App\Models\DepartmentTransfer::where('status', 'pending_head')
            ->where('from_dept_id', $user->department_id)
            ->count();
    } elseif ($user->isAdmin()) {
        $transferHeadBadge = \App\Models\DepartmentTransfer::where('status', 'pending_head')->count();
    }

    // Unread notifications count
    $notifCount = \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count();

    // Low stock items (supply hub items below min_stock_qty)
    $lowStockCount = 0;
    if ($supplyHub) {
        $lowStockCount = \App\Models\Item::where('department_id', $supplyHub->id)
            ->where('min_stock_qty', '>', 0)
            ->whereColumn('current_qty', '<', 'min_stock_qty')
            ->count();
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

            {{-- ── RIS section ── --}}
            @php $risActive = request()->is('ris*') || request()->is('ris-head*') || request()->is('ris-supply*'); @endphp
            @if($risActive)
                <div x-show="!collapsed" x-transition.opacity class="pt-2 pb-1 px-3">
                    <p class="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">Requisitions</p>
                </div>
            @endif

            {{-- My RIS (all roles) --}}
            @php $myRisActive = request()->routeIs('ris.index') || request()->routeIs('ris.show') || request()->routeIs('ris.create'); @endphp
            <a href="{{ route('ris.index') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                      {{ $myRisActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                @if($myRisActive)
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                @endif
                <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                <span x-show="!collapsed" x-transition.opacity>My RIS</span>
            </a>

            {{-- Head Approval Queue (dept heads + admin) --}}
            @if($user->is_head || $user->isAdmin())
                @php $headActive = request()->routeIs('ris.head.*'); @endphp
                <a href="{{ route('ris.head.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $headActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($headActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <span class="relative shrink-0">
                        <x-heroicon-o-check-badge class="w-5 h-5"/>
                        @if($risHeadBadge > 0)
                            <span class="absolute -top-1 -right-1 bg-purple-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                {{ $risHeadBadge > 9 ? '9+' : $risHeadBadge }}
                            </span>
                        @endif
                    </span>
                    <span x-show="!collapsed" x-transition.opacity>RIS Approvals</span>
                </a>
            @endif

            {{-- Supply Queue (supply hub staff + admin) --}}
            @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                @php $supplyActive = request()->routeIs('ris.supply.*'); @endphp
                <a href="{{ route('ris.supply.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $supplyActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($supplyActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <span class="relative shrink-0">
                        <x-heroicon-o-inbox-stack class="w-5 h-5"/>
                        @if($risSupplyBadge > 0)
                            <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                {{ $risSupplyBadge > 9 ? '9+' : $risSupplyBadge }}
                            </span>
                        @endif
                    </span>
                    <span x-show="!collapsed" x-transition.opacity>Supply Queue</span>
                </a>
            @endif

            {{-- ── Transfers section ── --}}
            @php $transferActive = request()->routeIs('transfers.*'); @endphp
            <a href="{{ route('transfers.index') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                      {{ $transferActive && !request()->routeIs('transfers.head.*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                @if($transferActive && !request()->routeIs('transfers.head.*'))
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                @endif
                <x-heroicon-o-arrows-right-left class="w-5 h-5 shrink-0"/>
                <span x-show="!collapsed" x-transition.opacity>Transfers</span>
            </a>

            @if($user->is_head || $user->isAdmin())
                @php $tHeadActive = request()->routeIs('transfers.head.*'); @endphp
                <a href="{{ route('transfers.head.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $tHeadActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($tHeadActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <span class="relative shrink-0">
                        <x-heroicon-o-check-badge class="w-5 h-5"/>
                        @if($transferHeadBadge > 0)
                            <span class="absolute -top-1 -right-1 bg-purple-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                {{ $transferHeadBadge > 9 ? '9+' : $transferHeadBadge }}
                            </span>
                        @endif
                    </span>
                    <span x-show="!collapsed" x-transition.opacity>Transfer Approvals</span>
                </a>
            @endif

            {{-- Assemblies --}}
            @php $asmActive = request()->routeIs('assemblies.*'); @endphp
            <a href="{{ route('assemblies.index') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                      {{ $asmActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                @if($asmActive)
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                @endif
                <x-heroicon-o-wrench-screwdriver class="w-5 h-5 shrink-0"/>
                <span x-show="!collapsed" x-transition.opacity>Assemblies</span>
            </a>

            {{-- IAR (supply + admin) --}}
            @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                @php $iarActive = request()->routeIs('iar.*'); @endphp
                <a href="{{ route('iar.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $iarActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($iarActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-heroicon-o-document-check class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>IAR Records</span>
                </a>
            @endif

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

                {{-- Departments (admin only) --}}
                @php $deptsActive = request()->is('departments*'); @endphp
                <a href="{{ route('departments.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $deptsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($deptsActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-heroicon-o-building-office class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>Departments</span>
                </a>

                {{-- Item Categories (admin only) --}}
                @php $catsActive = request()->is('item-categories*'); @endphp
                <a href="{{ route('item-categories.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $catsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($catsActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-heroicon-o-tag class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>Categories</span>
                </a>
            @endif

            {{-- Notifications --}}
            @php $notifActive = request()->routeIs('notifications.*'); @endphp
            <a href="{{ route('notifications.index') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                      {{ $notifActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                @if($notifActive)
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                @endif
                <span class="relative shrink-0">
                    <x-heroicon-o-bell class="w-5 h-5"/>
                    @if($notifCount > 0)
                        <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                            {{ $notifCount > 9 ? '9+' : $notifCount }}
                        </span>
                    @endif
                </span>
                <span x-show="!collapsed" x-transition.opacity>Notifications</span>
            </a>
        </nav>

        <div class="px-5 py-4 border-t border-surface-border" x-show="!collapsed" x-transition.opacity>
            {{-- Low stock alert (supply staff + admin) --}}
            @if($lowStockCount > 0 && ($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id)))
                <div class="mb-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs font-medium text-amber-700">
                        ⚠ {{ $lowStockCount }} item{{ $lowStockCount > 1 ? 's' : '' }} below min stock
                    </p>
                </div>
            @endif
            <p class="text-sm font-medium text-ink-heading truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-ink-muted truncate">{{ auth()->user()->email }}</p>
            @if(auth()->user()->department)
                <p class="text-xs text-primary-600 font-medium truncate mt-0.5">
                    {{ auth()->user()->department->name }}
                    @if(auth()->user()->is_head)
                        · <span class="text-amber-600">Head</span>
                    @endif
                </p>
            @endif
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
