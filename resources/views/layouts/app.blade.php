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

    // Transaction approval badge (receive + release pending)
    $approvalBadge = 0;
    if ($user->is_head || $user->isAdmin()) {
        $approvalBadge = \App\Models\Transaction::where('head_approval_status', 'pending')
            ->when(! $user->isAdmin(), fn($q) => $q->where('department_id', $user->department_id))
            ->count();
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

    $combinedApprovalBadge = $approvalBadge + $risHeadBadge + $transferHeadBadge;
@endphp

<div class="flex min-h-screen" x-data="{
    collapsed:    localStorage.getItem('sidebar-collapsed') === '1',
    sInventory:   localStorage.getItem('nav-inventory')    !== '0',
    sApprovals:   localStorage.getItem('nav-approvals')    !== '0',
    sRequisitions:localStorage.getItem('nav-requisitions') !== '0',
    sOperations:  localStorage.getItem('nav-operations')   !== '0',
    sFinance:     localStorage.getItem('nav-finance')      !== '0',
    sAdmin:       localStorage.getItem('nav-admin')        !== '0',
}">

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

        <nav class="flex-1 px-3 py-4">

            {{-- Dashboard — always visible, no section header --}}
            @php $active = request()->is('/'); @endphp
            <a href="{{ route('dashboard') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition mb-1
                      {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
               title="Dashboard">
                @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                <x-heroicon-o-home class="w-5 h-5 shrink-0"/>
                <span x-show="!collapsed" x-transition.opacity>Dashboard</span>
            </a>

            {{-- ── INVENTORY ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sInventory = !sInventory; localStorage.setItem('nav-inventory', sInventory ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Inventory</span>
                    <span :class="{ '-rotate-90': !sInventory }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sInventory" class="space-y-0.5 mt-0.5">
                    @php $active = request()->is('receive*'); @endphp
                    <a href="{{ route('receive.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Receive">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrow-down-tray class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Receive</span>
                    </a>
                    @php $active = request()->is('release*'); @endphp
                    <a href="{{ route('release.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Release">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Release</span>
                    </a>
                    @php $active = request()->is('acknowledge*'); @endphp
                    <a href="{{ route('acknowledge.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Acknowledge">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-check-circle class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Acknowledge</span>
                    </a>
                    @php $active = request()->routeIs('transactions*'); @endphp
                    <a href="{{ route('transactions.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transactions">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Transactions</span>
                    </a>
                    @php $active = request()->routeIs('items*'); @endphp
                    <a href="{{ route('items.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Inventory">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-cube class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Inventory</span>
                    </a>
                </div>
            </div>

            {{-- ── APPROVALS (head + admin only) ── --}}
            @if($user->is_head || $user->isAdmin())
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sApprovals = !sApprovals; localStorage.setItem('nav-approvals', sApprovals ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <div class="flex items-center gap-1.5">
                        <span>Approvals</span>
                        @if($combinedApprovalBadge > 0)
                            <span class="bg-amber-500 text-white text-[10px] rounded-full px-1.5 leading-4 font-semibold">
                                {{ $combinedApprovalBadge > 9 ? '9+' : $combinedApprovalBadge }}
                            </span>
                        @endif
                    </div>
                    <span :class="{ '-rotate-90': !sApprovals }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sApprovals" class="space-y-0.5 mt-0.5">
                    @php $approvalsActive = request()->routeIs('approvals.*'); @endphp
                    <a href="{{ route('approvals.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $approvalsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Approvals">
                        @if($approvalsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <span class="relative shrink-0">
                            <x-heroicon-o-clipboard-document-check class="w-5 h-5"/>
                            @if($approvalBadge > 0)
                                <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                    {{ $approvalBadge > 9 ? '9+' : $approvalBadge }}
                                </span>
                            @endif
                        </span>
                        <span x-show="!collapsed" x-transition.opacity>Approvals</span>
                    </a>
                    @php $headActive = request()->routeIs('ris.head.*'); @endphp
                    <a href="{{ route('ris.head.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $headActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="RIS Approvals">
                        @if($headActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
                    @php $tHeadActive = request()->routeIs('transfers.head.*'); @endphp
                    <a href="{{ route('transfers.head.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $tHeadActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transfer Approvals">
                        @if($tHeadActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
                </div>
            </div>
            @endif

            {{-- ── REQUISITIONS ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sRequisitions = !sRequisitions; localStorage.setItem('nav-requisitions', sRequisitions ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Requisitions</span>
                    <span :class="{ '-rotate-90': !sRequisitions }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sRequisitions" class="space-y-0.5 mt-0.5">
                    @php $myRisActive = request()->routeIs('ris.index') || request()->routeIs('ris.show') || request()->routeIs('ris.create'); @endphp
                    <a href="{{ route('ris.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $myRisActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="My RIS">
                        @if($myRisActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>My RIS</span>
                    </a>
                    @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                        @php $supplyActive = request()->routeIs('ris.supply.*'); @endphp
                        <a href="{{ route('ris.supply.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $supplyActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Supply Queue">
                            @if($supplyActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
                </div>
            </div>

            {{-- ── OPERATIONS ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sOperations = !sOperations; localStorage.setItem('nav-operations', sOperations ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Operations</span>
                    <span :class="{ '-rotate-90': !sOperations }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sOperations" class="space-y-0.5 mt-0.5">
                    @php $transferActive = request()->routeIs('transfers.*') && !request()->routeIs('transfers.head.*'); @endphp
                    <a href="{{ route('transfers.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $transferActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transfers">
                        @if($transferActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrows-right-left class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Transfers</span>
                    </a>
                    @php $asmActive = request()->routeIs('assemblies.*'); @endphp
                    <a href="{{ route('assemblies.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $asmActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Assemblies">
                        @if($asmActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Assemblies</span>
                    </a>
                    @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                        @php $iarActive = request()->routeIs('iar.*'); @endphp
                        <a href="{{ route('iar.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $iarActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="IAR Records">
                            @if($iarActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-document-check class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>IAR Records</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- ── FINANCE ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sFinance = !sFinance; localStorage.setItem('nav-finance', sFinance ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Finance</span>
                    <span :class="{ '-rotate-90': !sFinance }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sFinance" class="space-y-0.5 mt-0.5">
                    @php $pcActive = request()->is('petty-cash*'); @endphp
                    <a href="{{ route('petty-cash.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $pcActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Petty Cash">
                        @if($pcActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
                </div>
            </div>

            {{-- ── ADMIN (admin + accounting) ── --}}
            @if($user->canManageUsers() || $user->canAccessReports())
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sAdmin = !sAdmin; localStorage.setItem('nav-admin', sAdmin ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Admin</span>
                    <span :class="{ '-rotate-90': !sAdmin }" class="transition-transform inline-flex"><x-heroicon-o-chevron-down class="w-3 h-3"/></span>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sAdmin" class="space-y-0.5 mt-0.5">
                    @if($user->canAccessReports())
                        @php $repActive = request()->is('reports*'); @endphp
                        <a href="{{ route('reports.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $repActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Reports">
                            @if($repActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Reports</span>
                        </a>
                    @endif
                    @if($user->canManageUsers())
                        @php $usersActive = request()->is('users*'); @endphp
                        <a href="{{ route('users.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $usersActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Users">
                            @if($usersActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-users class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Users</span>
                        </a>
                        @php $deptsActive = request()->is('departments*'); @endphp
                        <a href="{{ route('departments.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $deptsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Departments">
                            @if($deptsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-building-office class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Departments</span>
                        </a>
                        @php $catsActive = request()->is('item-categories*'); @endphp
                        <a href="{{ route('item-categories.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $catsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Categories">
                            @if($catsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-tag class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Categories</span>
                        </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- Notifications — standalone (always visible) --}}
            <div class="mt-2">
                @php $notifActive = request()->routeIs('notifications.*'); @endphp
                <a href="{{ route('notifications.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $notifActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                   title="Notifications">
                    @if($notifActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
            </div>

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
