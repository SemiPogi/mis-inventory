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

    {{-- Row 3: Pending acknowledgments table --}}
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
