<x-app-layout>
    <x-page-header title="Pending RIS Approvals" subtitle="Review and approve RIS requests from your department.">
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($pending->isEmpty())
            <x-empty-state icon="check-circle" title="No pending approvals" hint="All RIS requests have been reviewed."/>
        @else
            <x-table :headers="['RIS #', 'Requested By', 'Purpose', 'Items', 'Date', 'Actions']">
                @foreach($pending as $ris)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('ris.show', $ris) }}" class="hover:underline">{{ $ris->ris_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ris->requestedBy->name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted max-w-xs truncate">{{ $ris->purpose }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ris->items->count() }} items</td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $ris->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2" x-data="{ rejecting: false }">
                                <form method="POST" action="{{ route('ris.head.approve', $ris) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Approve</button>
                                </form>
                                <button type="button" @click="rejecting = !rejecting" class="text-xs font-medium text-rose-500 hover:text-rose-700">Reject</button>
                                <div x-show="rejecting" x-transition class="absolute z-10 mt-2 bg-surface-tile border border-surface-border rounded-lg p-3 shadow-lg w-64">
                                    <form method="POST" action="{{ route('ris.head.reject', $ris) }}" class="space-y-2">
                                        @csrf @method('PATCH')
                                        <textarea name="notes" rows="2" required
                                                  class="w-full rounded border border-surface-border bg-surface-page text-ink-body px-2 py-1.5 text-xs"
                                                  placeholder="Reason for rejection…"></textarea>
                                        <x-button type="submit" variant="ghost" class="text-xs text-rose-600 w-full">Confirm Reject</x-button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
