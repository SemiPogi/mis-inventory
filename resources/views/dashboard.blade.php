<x-app-layout>
    <x-page-header title="Dashboard" subtitle="MIS Office Inventory Overview"/>

    {{-- Row 1: Stat tiles --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4"
         x-data x-init="$stagger($el)" data-anim="stagger">
        <x-stat-tile label="Items in Stock"        :value="$totalInStock"  icon="cube"/>
        <x-stat-tile label="Total Released"        :value="$totalReleased" icon="paper-airplane"/>
        <x-stat-tile label="Pending Acknowledgment" :value="$pendingAck"   icon="clock" variant="hero"/>
        <x-stat-tile label="Acknowledged"          :value="$acknowledged"  icon="check-badge"/>
    </div>

    {{-- Row 2: hero activity + 2 side tiles --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
        <x-bento-card variant="hero" class="lg:col-span-2">
            <p class="text-xs uppercase tracking-wide opacity-80 font-medium">Weekly Activity</p>
            <p class="text-3xl font-bold mt-1">{{ array_sum($weeklyActivity) }} <span class="text-sm font-medium opacity-80">releases / 7d</span></p>
            <div class="mt-3 h-16">
                <x-sparkline :data="$weeklyActivity" color="#ffffff"/>
            </div>
        </x-bento-card>

        <x-bento-card>
            <p class="text-xs uppercase tracking-wide text-ink-muted font-medium">Top Office (this month)</p>
            <p class="text-xl font-semibold text-ink-heading mt-2">{{ $topOffice ?? '—' }}</p>
        </x-bento-card>

        <x-bento-card>
            <p class="text-xs uppercase tracking-wide text-ink-muted font-medium">Top Item (this month)</p>
            <p class="text-xl font-semibold text-ink-heading mt-2">{{ $topItem ?? '—' }}</p>
        </x-bento-card>
    </div>

    {{-- Expiry Alert --}}
    @if($expiringItems->isNotEmpty())
    <x-bento-card :padded="false" class="mb-4">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500"/>
                <h2 class="text-sm font-semibold text-ink-heading">Expiry Alerts</h2>
            </div>
            <a href="{{ route('items.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all items</a>
        </div>
        <x-table :headers="['Item', 'Category', 'Stock', 'Expiry Date', 'Status']">
            @foreach($expiringItems as $ei)
                <x-table.row>
                    <td class="px-6 py-3 font-medium text-ink-heading">
                        <a href="{{ route('items.show', $ei) }}" class="hover:text-primary-600">{{ $ei->name }}</a>
                    </td>
                    <td class="px-6 py-3 text-sm text-ink-muted">{{ $ei->category ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-ink-body">{{ $ei->current_qty }} {{ $ei->unit }}</td>
                    <td class="px-6 py-3 text-sm text-ink-body">{{ $ei->expiry_date->format('M d, Y') }}</td>
                    <td class="px-6 py-3">
                        @if($ei->expiryStatus() === 'expired')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Expired</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Expires {{ $ei->expiry_date->diffForHumans() }}</span>
                        @endif
                    </td>
                </x-table.row>
            @endforeach
        </x-table>
    </x-bento-card>
    @endif

    {{-- Row 3: Petty Cash tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <x-stat-tile label="Petty Cash This Month" :value="'₱' . number_format($pcThisMonth, 2)" icon="banknotes" color="amber" />
        <x-stat-tile label="Vouchers This Month" :value="$pcVouchersThisMonth" icon="document-text" color="primary" />
        @if(auth()->user()->canCreateVoucher())
            <x-stat-tile label="Pending Acknowledgement" :value="$pcPendingAck" icon="clock" color="rose" />
        @endif
        @if(auth()->user()->canSettleVoucher())
            <x-stat-tile label="Pending Settlement" :value="$pcPendingSettle" icon="banknotes" color="amber" />
        @endif
    </div>

    {{-- Row 4: Recent petty cash vouchers --}}
    @if($recentVouchers->isNotEmpty())
    <x-bento-card :padded="false" class="mb-4">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">Recent Petty Cash</h2>
            <a href="{{ route('petty-cash.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-ink-muted uppercase border-b border-surface-border">
                    <th class="px-6 py-3">Voucher</th>
                    <th class="px-6 py-3">Store</th>
                    <th class="px-6 py-3 text-right">Amount</th>
                    <th class="px-6 py-3 text-right">Change</th>
                    <th class="px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-border">
                @foreach($recentVouchers as $v)
                    <tr>
                        <td class="px-6 py-3 font-mono text-primary-700">
                            <a href="{{ route('petty-cash.show', $v) }}" class="hover:underline">{{ $v->voucher_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-ink-muted">{{ $v->store_name }}</td>
                        <td class="px-6 py-3 text-right">₱{{ number_format($v->total_amount, 2) }}</td>
                        <td class="px-6 py-3 text-right {{ $v->change_amount > 0 ? 'text-amber-600' : 'text-ink-muted' }}">
                            ₱{{ number_format($v->change_amount, 2) }}
                        </td>
                        <td class="px-6 py-3"><x-status-badge :status="$v->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-bento-card>
    @endif

    {{-- Row 5: Pending acknowledgments table --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">Pending Acknowledgments</h2>
            <a href="{{ route('acknowledge.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all</a>
        </div>

        @if($pendingTransactions->isEmpty())
            <x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments." />
        @else
            <x-table :headers="['Item', 'Released To', 'Office', 'Qty', 'Date', '']">
                @foreach($pendingTransactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->date_released }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('acknowledge.index') }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Acknowledge →</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
