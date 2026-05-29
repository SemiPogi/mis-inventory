<x-app-layout>
    <x-page-header title="Department Transfers" subtitle="Track inter-department item transfers.">
        <x-slot name="actions">
            @if(auth()->user()->canCreateVoucher())
                <x-button href="{{ route('transfers.create') }}" variant="primary">
                    <x-heroicon-o-plus class="w-4 h-4"/> New Transfer
                </x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($transfers->isEmpty())
            <x-empty-state icon="arrows-right-left" title="No transfers yet" hint="Create a transfer to move items between departments."/>
        @else
            <x-table :headers="['Transfer #', 'From', 'To', 'Items', 'Status', 'Date', '']">
                @foreach($transfers as $t)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('transfers.show', $t) }}" class="hover:underline">{{ $t->transfer_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $t->fromDept->name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $t->toDept->name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $t->items->count() }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                {{ match($t->status) {
                                    'pending_head' => 'bg-purple-100 text-purple-700',
                                    'approved'     => 'bg-blue-100 text-blue-700',
                                    'rejected'     => 'bg-rose-100 text-rose-700',
                                    'completed'    => 'bg-emerald-100 text-emerald-700',
                                    default        => 'bg-gray-100 text-gray-700',
                                } }}">
                                {{ $t->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $t->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('transfers.show', $t) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View</a>
                                @if($t->isApproved() && auth()->user()->department_id === $t->to_dept_id)
                                    <form method="POST" action="{{ route('transfers.acknowledge', $t) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-medium text-sky-600 hover:text-sky-700">Acknowledge</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-3">{{ $transfers->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
