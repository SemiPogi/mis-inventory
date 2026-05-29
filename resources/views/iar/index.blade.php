<x-app-layout>
    <x-page-header title="Inspection & Acceptance Reports" subtitle="Record supplier deliveries and stock acceptance.">
        <x-slot name="actions">
            <x-button href="{{ route('iar.create') }}" variant="primary">
                <x-heroicon-o-plus class="w-4 h-4"/> New IAR
            </x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($records->isEmpty())
            <x-empty-state icon="document-check" title="No IARs yet" hint="Create an IAR to record a supplier delivery."/>
        @else
            <x-table :headers="['IAR #', 'Supplier', 'PO #', 'Items', 'Status', 'Date', '']">
                @foreach($records as $iar)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('iar.show', $iar) }}" class="hover:underline">{{ $iar->iar_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $iar->supplier }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted font-mono">{{ $iar->purchase_order_no ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $iar->items->count() }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                {{ match($iar->status) {
                                    'draft'    => 'bg-gray-100 text-gray-700',
                                    'accepted' => 'bg-emerald-100 text-emerald-700',
                                    'rejected' => 'bg-rose-100 text-rose-700',
                                    default    => 'bg-gray-100 text-gray-700',
                                } }}">
                                {{ $iar->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $iar->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('iar.show', $iar) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-3">{{ $records->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
