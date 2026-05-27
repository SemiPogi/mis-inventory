<x-app-layout>
    <x-page-header title="Reports" subtitle="Audit logs and summaries for inventory and petty cash"/>

    @php
        $inventoryReports = [
            'received'        => ['label' => 'Received Items',       'icon' => 'arrow-down-tray',          'filters' => ['from','to','item']],
            'released'        => ['label' => 'Released Items',        'icon' => 'arrow-up-tray',            'filters' => ['from','to','item','office']],
            'movement'        => ['label' => 'Stock Movement',        'icon' => 'arrows-up-down',           'filters' => ['from','to','item']],
            'snapshot'        => ['label' => 'Current Stock Snapshot','icon' => 'cube',                     'filters' => []],
            'acknowledgement' => ['label' => 'Acknowledgement Status','icon' => 'check-badge',              'filters' => ['from','to','status']],
        ];
        $pettyCashReports = [
            'ledger'      => ['label' => 'Voucher Ledger',       'icon' => 'document-text',     'filters' => ['from','to','status','officer']],
            'monthly'     => ['label' => 'Monthly Summary',      'icon' => 'calendar-days',     'filters' => ['year']],
            'outstanding' => ['label' => 'Outstanding Changes',  'icon' => 'exclamation-circle', 'filters' => []],
            'purchases'   => ['label' => 'Item Purchase History','icon' => 'shopping-bag',       'filters' => ['from','to','item']],
        ];

        $activeReports  = $tab === 'petty-cash' ? $pettyCashReports : $inventoryReports;
        $currentFilters = ($tab && $type && isset($activeReports[$type])) ? $activeReports[$type]['filters'] : [];
        $routeName      = $tab === 'petty-cash' ? 'reports.petty-cash' : 'reports.inventory';
    @endphp

    {{-- Tab bar --}}
    <div class="flex gap-1 mb-5 border-b border-surface-border">
        <a href="{{ route('reports.inventory', 'received') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                  {{ $tab === 'inventory' ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-muted hover:text-ink-heading hover:border-surface-border' }}">
            <span class="flex items-center gap-2">
                <x-heroicon-o-cube class="w-4 h-4"/>
                Inventory
            </span>
        </a>
        <a href="{{ route('reports.petty-cash', 'ledger') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                  {{ $tab === 'petty-cash' ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-muted hover:text-ink-heading hover:border-surface-border' }}">
            <span class="flex items-center gap-2">
                <x-heroicon-o-banknotes class="w-4 h-4"/>
                Petty Cash
            </span>
        </a>
    </div>

    @if($tab)
    <div class="flex gap-5 items-start">

        {{-- Sidebar: report list --}}
        <div class="w-52 shrink-0 space-y-1">
            @foreach($activeReports as $key => $report)
                <a href="{{ route($routeName, $key) }}"
                   class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $type === $key
                              ? 'bg-primary-50 text-primary-700 font-medium'
                              : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    <x-dynamic-component :component="'heroicon-o-' . $report['icon']" class="w-4 h-4 shrink-0"/>
                    {{ $report['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Main panel --}}
        <div class="flex-1 min-w-0">

            {{-- Filter bar --}}
            @if($type)
            <x-bento-card class="mb-4">
                <form method="GET" action="{{ route($routeName, $type) }}"
                      class="flex flex-wrap gap-3 items-end">

                    @if(in_array('item', $currentFilters))
                    <div class="flex-1 min-w-36">
                        <x-label for="item">Item Name</x-label>
                        <x-input id="item" name="item" :value="request('item')" placeholder="Filter by item…"/>
                    </div>
                    @endif

                    @if(in_array('office', $currentFilters))
                    <div class="flex-1 min-w-36">
                        <x-label for="office">Office</x-label>
                        <x-input id="office" name="office" :value="request('office')" placeholder="Office name…"/>
                    </div>
                    @endif

                    @if(in_array('officer', $currentFilters))
                    <div class="flex-1 min-w-36">
                        <x-label for="officer">Releasing Officer</x-label>
                        <x-input id="officer" name="officer" :value="request('officer')" placeholder="Officer name…"/>
                    </div>
                    @endif

                    @if(in_array('status', $currentFilters))
                    <div>
                        <x-label for="status">Status</x-label>
                        <x-select id="status" name="status">
                            @if($tab === 'inventory')
                                <option value="">All</option>
                                <option value="pending"      @selected(request('status') === 'pending')>Pending</option>
                                <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
                            @else
                                <option value="">All</option>
                                <option value="submitted"    @selected(request('status') === 'submitted')>Submitted</option>
                                <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
                                <option value="settled"      @selected(request('status') === 'settled')>Settled</option>
                            @endif
                        </x-select>
                    </div>
                    @endif

                    @if(in_array('year', $currentFilters))
                    <div>
                        <x-label for="year">Year</x-label>
                        <x-select id="year" name="year">
                            @foreach(range(now()->year, 2024) as $y)
                                <option value="{{ $y }}" @selected(request('year', now()->year) == $y)>{{ $y }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    @endif

                    @if(in_array('from', $currentFilters))
                    <div>
                        <x-label for="from">From</x-label>
                        <x-input id="from" name="from" type="date" :value="request('from')"/>
                    </div>
                    @endif

                    @if(in_array('to', $currentFilters))
                    <div>
                        <x-label for="to">To</x-label>
                        <x-input id="to" name="to" type="date" :value="request('to')"/>
                    </div>
                    @endif

                    <div class="flex gap-2">
                        @if(request()->hasAny(['from','to','item','office','officer','status','year']))
                            <x-button as="a" variant="ghost" href="{{ route($routeName, $type) }}">Clear</x-button>
                        @endif
                        <x-button type="submit" variant="primary">
                            <x-heroicon-o-funnel class="w-4 h-4"/>
                            Run Report
                        </x-button>
                    </div>

                </form>
            </x-bento-card>
            @endif

            {{-- Results --}}
            <x-bento-card :padded="false">
                {{-- Card header --}}
                <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-ink-heading">
                            {{ $title ?? ($type ? ($activeReports[$type]['label'] ?? 'Report') : 'Select a report') }}
                        </h2>
                        @if($rows)
                            <p class="text-xs text-ink-muted mt-0.5">{{ count($rows) }} {{ Str::plural('row', count($rows)) }}</p>
                        @endif
                    </div>
                    @if($type && !empty($rows))
                        <a href="{{ route($routeName, $type) . '?' . http_build_query(array_merge(request()->except('export'), ['export' => '1'])) }}"
                           class="flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700 transition">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                            Export CSV
                        </a>
                    @endif
                </div>

                @if(!$type)
                    <x-empty-state icon="chart-bar" title="Choose a report" hint="Select a report type from the sidebar to get started."/>
                @elseif(empty($rows))
                    <x-empty-state icon="inbox" title="No data found" hint="Try adjusting the filters or date range."/>
                @else
                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-ink-muted uppercase border-b border-surface-border bg-surface-page">
                                    @foreach($headers as $h)
                                        <th class="px-5 py-3 font-medium whitespace-nowrap">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-border">
                                @foreach($rows as $row)
                                    <tr class="hover:bg-surface-page transition-colors">
                                        @foreach($row as $i => $cell)
                                            @php
                                                $isFirst = $i === 0;
                                                $isAmount = is_string($cell) && str_starts_with($cell, '₱');
                                                $isStatus = in_array($cell, ['pending','acknowledged','received','released','submitted','settled']);
                                            @endphp
                                            <td class="px-5 py-3 whitespace-nowrap
                                                       {{ $isFirst ? 'font-medium text-ink-heading' : 'text-ink-body' }}
                                                       {{ $isAmount ? 'font-mono tabular-nums' : '' }}">
                                                @if($isStatus)
                                                    <x-status-badge :status="$cell"/>
                                                @else
                                                    {{ $cell ?? '—' }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-bento-card>

        </div>
    </div>

    @else
        {{-- Landing state --}}
        <x-empty-state icon="chart-bar" title="Reports Hub"
                       hint="Choose Inventory or Petty Cash from the tabs above to begin."/>
    @endif

</x-app-layout>
