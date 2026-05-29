<x-app-layout>
    <x-page-header :title="$iar->iar_number" :subtitle="'Status: ' . $iar->statusLabel()">
        <x-slot name="actions">
            <x-button href="{{ route('iar.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif
    @if(session('error'))
        <x-toast type="error" :message="session('error')"/>
    @endif

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 space-y-4">

            {{-- Items table --}}
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
                    <p class="text-sm font-medium text-ink-heading">Delivered Items</p>
                    @php $total = $iar->totalValue(); @endphp
                    @if($total > 0)
                        <p class="text-sm text-ink-muted">Total Value: <span class="font-semibold text-ink-heading">₱{{ number_format($total, 2) }}</span></p>
                    @endif
                </div>
                <x-table :headers="['Item', 'Unit', 'Delivered', 'Accepted', 'Unit Cost', 'Total', 'Remarks']">
                    @foreach($iar->items as $item)
                        <x-table.row>
                            <td class="px-6 py-3 text-sm font-medium text-ink-heading">
                                {{ $item->item_name }}
                                @if($item->description)
                                    <span class="block text-xs text-ink-muted font-normal">{{ $item->description }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-ink-body">{{ $item->unit }}</td>
                            <td class="px-6 py-3 text-sm text-center text-ink-body">{{ $item->qty_delivered }}</td>
                            <td class="px-6 py-3 text-sm text-center font-medium text-emerald-700">{{ $item->qty_accepted }}</td>
                            <td class="px-6 py-3 text-sm text-ink-body text-right font-mono">₱{{ number_format($item->unit_cost, 2) }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-ink-body text-right font-mono">₱{{ number_format($item->totalCost(), 2) }}</td>
                            <td class="px-6 py-3 text-sm text-ink-muted">{{ $item->remarks ?? '—' }}</td>
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-bento-card>

            {{-- Accept/Reject actions for Draft --}}
            @if($iar->isDraft())
                <x-bento-card>
                    <p class="text-sm font-medium text-ink-heading mb-3">Acceptance Decision</p>
                    <div class="flex gap-3 flex-wrap" x-data="{ rejecting: false }">
                        <form method="POST" action="{{ route('iar.accept', $iar) }}">
                            @csrf @method('PATCH')
                            <x-button type="submit" variant="primary">
                                <x-heroicon-o-check class="w-4 h-4"/> Accept Delivery
                            </x-button>
                        </form>
                        <div>
                            <x-button variant="ghost" @click="rejecting = !rejecting" type="button">
                                <x-heroicon-o-x-mark class="w-4 h-4"/> Reject
                            </x-button>
                            <div x-show="rejecting" x-transition class="mt-3 space-y-2">
                                <form method="POST" action="{{ route('iar.reject', $iar) }}" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <textarea name="notes" rows="2" required
                                              class="w-full rounded border border-surface-border bg-surface-tile text-ink-body px-3 py-2 text-sm"
                                              placeholder="Reason for rejection…"></textarea>
                                    <x-button type="submit" variant="ghost" class="text-rose-600">Confirm Reject</x-button>
                                </form>
                            </div>
                        </div>
                    </div>
                </x-bento-card>
            @endif

            {{-- Attachments --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Attachments</p>
                @if($iar->attachments->isEmpty())
                    <p class="text-xs text-ink-muted italic mb-3">No attachments.</p>
                @else
                    <ul class="space-y-1 mb-4">
                        @foreach($iar->attachments as $att)
                            <li class="flex items-center justify-between text-sm">
                                <a href="{{ $att->url() }}" target="_blank" class="text-primary-600 hover:underline truncate">{{ $att->original_name }}</a>
                                <span class="text-xs text-ink-muted ml-4 shrink-0">{{ $att->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($iar->isDraft())
                    <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="flex gap-2 items-end">
                        @csrf
                        <input type="hidden" name="attachable_type" value="iar"/>
                        <input type="hidden" name="attachable_id" value="{{ $iar->id }}"/>
                        <input type="file" name="file" class="text-sm text-ink-body file:mr-2 file:py-1 file:px-3 file:rounded file:border file:border-surface-border file:bg-surface-tile file:text-xs"/>
                        <x-button type="submit" variant="ghost" class="text-xs shrink-0">Upload</x-button>
                    </form>
                @endif
            </x-bento-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">IAR Info</p>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-ink-muted text-xs">IAR #</dt><dd class="font-mono font-medium text-primary-700">{{ $iar->iar_number }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Supplier</dt><dd class="font-medium text-ink-heading">{{ $iar->supplier }}</dd></div>
                    @if($iar->purchase_order_no)
                        <div><dt class="text-ink-muted text-xs">PO No.</dt><dd class="font-mono text-ink-body">{{ $iar->purchase_order_no }}</dd></div>
                    @endif
                    @if($iar->date_of_delivery)
                        <div><dt class="text-ink-muted text-xs">Date of Delivery</dt><dd class="text-ink-body">{{ $iar->date_of_delivery->format('M d, Y') }}</dd></div>
                    @endif
                    @if($iar->date_of_inspection)
                        <div><dt class="text-ink-muted text-xs">Date of Inspection</dt><dd class="text-ink-body">{{ $iar->date_of_inspection->format('M d, Y') }}</dd></div>
                    @endif
                    <div><dt class="text-ink-muted text-xs">Created By</dt><dd class="text-ink-body">{{ $iar->createdBy->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Date Created</dt><dd class="text-ink-body">{{ $iar->created_at->format('M d, Y') }}</dd></div>
                    @if($iar->notes)
                        <div><dt class="text-ink-muted text-xs">Notes</dt><dd class="text-ink-body">{{ $iar->notes }}</dd></div>
                    @endif
                </dl>
            </x-bento-card>
        </div>
    </div>
</x-app-layout>
