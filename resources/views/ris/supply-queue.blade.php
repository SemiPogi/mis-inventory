<x-app-layout>
    <x-page-header title="RIS Supply Queue" subtitle="Process incoming requisitions from all departments.">
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    {{-- Filters --}}
    <x-bento-card>
        <form method="GET" action="{{ route('ris.supply.index') }}" class="flex gap-3 flex-wrap items-end">
            <div>
                <x-label for="dept">Department</x-label>
                <x-select id="dept" name="dept">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(request('dept') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <x-label for="status">Status</x-label>
                <x-select id="status" name="status">
                    <option value="">All</option>
                    <option value="pending_supply" @selected(request('status') === 'pending_supply')>Pending Supply</option>
                    <option value="issued" @selected(request('status') === 'issued')>Issued</option>
                </x-select>
            </div>
            <x-button type="submit" variant="primary">Filter</x-button>
            <x-button href="{{ route('ris.supply.index') }}" variant="ghost">Clear</x-button>
        </form>
    </x-bento-card>

    <x-bento-card :padded="false">
        @if($queue->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="No pending RIS" hint="All requisitions have been processed."/>
        @else
            <x-table :headers="['RIS #', 'Department', 'Purpose', 'Items', 'Status', 'Date', 'Action']">
                @foreach($queue as $ris)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('ris.show', $ris) }}" class="hover:underline">{{ $ris->ris_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ris->requestingDept->name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted max-w-xs truncate">{{ $ris->purpose }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ris->items->count() }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $ris->status === 'pending_supply' ? 'bg-blue-100 text-blue-700' : 'bg-sky-100 text-sky-700' }}">
                                {{ $ris->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $ris->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3">
                            @if($ris->isPendingSupply())
                                <a href="{{ route('ris.supply.review', $ris) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Process</a>
                            @else
                                <a href="{{ route('ris.show', $ris) }}" class="text-xs font-medium text-ink-muted hover:text-primary-600">View</a>
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-3">{{ $queue->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
