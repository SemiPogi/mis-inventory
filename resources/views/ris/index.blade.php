<x-app-layout>
    <x-page-header title="RIS Requests" subtitle="Requisition and Issue Slips for your department.">
        <x-slot name="actions">
            @if(auth()->user()->canCreateVoucher())
                <x-button href="{{ route('ris.create') }}" variant="primary">
                    <x-heroicon-o-plus class="w-4 h-4"/>
                    New RIS
                </x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($ris->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="No RIS requests yet" hint="Create a new RIS to request items from Supply."/>
        @else
            <x-table :headers="['RIS #', 'Department', 'Purpose', 'Items', 'Status', 'Date', '']">
                @foreach($ris as $r)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('ris.show', $r) }}" class="hover:underline">{{ $r->ris_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $r->requestingDept->name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted max-w-xs truncate">{{ $r->purpose }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $r->items->count() }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                {{ match($r->status) {
                                    'draft'          => 'bg-gray-100 text-gray-700',
                                    'pending_head'   => 'bg-purple-100 text-purple-700',
                                    'pending_supply' => 'bg-blue-100 text-blue-700',
                                    'issued'         => 'bg-sky-100 text-sky-700',
                                    'completed'      => 'bg-emerald-100 text-emerald-700',
                                    'rejected'       => 'bg-rose-100 text-rose-700',
                                    default          => 'bg-gray-100 text-gray-700',
                                } }}">
                                {{ $r->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $r->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('ris.show', $r) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View</a>
                                @if($r->isCompleted())
                                    <a href="{{ route('ris.print', $r) }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-700" target="_blank">Print</a>
                                @endif
                                @if($r->isIssued() && auth()->user()->department_id === $r->requesting_dept_id)
                                    <form method="POST" action="{{ route('ris.acknowledge', $r) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-medium text-sky-600 hover:text-sky-700">Acknowledge</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-3">{{ $ris->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
