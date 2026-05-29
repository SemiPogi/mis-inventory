<x-app-layout>
    <x-page-header :title="$transfer->transfer_number"
                   :subtitle="'Status: ' . $transfer->statusLabel()">
        <x-slot name="actions">
            <x-button href="{{ route('transfers.index') }}" variant="ghost">← Back</x-button>
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

            {{-- Status Timeline --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-4">Status Timeline</p>
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    @foreach(['pending_head' => 'Head Approval', 'approved' => 'Approved', 'completed' => 'Completed'] as $s => $label)
                        @php
                            $steps = ['pending_head','approved','completed'];
                            $current = array_search($transfer->status, $steps);
                            $step    = array_search($s, $steps);
                            $done    = $current !== false && $step <= $current && !$transfer->isRejected();
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1.5 rounded-full font-medium
                                {{ $transfer->status === $s ? 'bg-primary-600 text-white' : ($done ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500') }}">
                                {{ $label }}
                            </span>
                            @if(!$loop->last) <span class="text-ink-muted">→</span> @endif
                        </div>
                    @endforeach
                    @if($transfer->isRejected())
                        <span class="px-3 py-1.5 rounded-full font-medium bg-rose-100 text-rose-700">Rejected</span>
                    @endif
                </div>
            </x-bento-card>

            {{-- Items --}}
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border">
                    <p class="text-sm font-medium text-ink-heading">Items Being Transferred</p>
                </div>
                <x-table :headers="['Item', 'Unit', 'Qty']">
                    @foreach($transfer->items as $item)
                        <x-table.row>
                            <td class="px-6 py-3 text-sm font-medium text-ink-heading">{{ $item->item_name_snapshot }}</td>
                            <td class="px-6 py-3 text-sm text-ink-body">{{ $item->unit }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-primary-700">{{ $item->qty }}</td>
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-bento-card>

            {{-- Acknowledge action --}}
            @if($transfer->isApproved() && auth()->user()->department_id === $transfer->to_dept_id)
                <x-bento-card>
                    <p class="text-sm font-medium text-ink-heading mb-2">Acknowledge Receipt</p>
                    <p class="text-xs text-ink-muted mb-3">Confirm items received. They will be added to your inventory.</p>
                    <form method="POST" action="{{ route('transfers.acknowledge', $transfer) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary">
                            <x-heroicon-o-check-circle class="w-4 h-4"/> Acknowledge Receipt
                        </x-button>
                    </form>
                </x-bento-card>
            @endif

            {{-- Attachments --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Attachments</p>
                @if($transfer->attachments->isEmpty())
                    <p class="text-xs text-ink-muted italic mb-3">No attachments yet.</p>
                @else
                    <ul class="space-y-1 mb-4">
                        @foreach($transfer->attachments as $att)
                            <li class="flex items-center justify-between text-sm">
                                <a href="{{ $att->url() }}" target="_blank" class="text-primary-600 hover:underline truncate">
                                    {{ $att->original_name }}
                                </a>
                                <span class="text-xs text-ink-muted ml-4 shrink-0">{{ $att->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="flex gap-2 items-end">
                    @csrf
                    <input type="hidden" name="attachable_type" value="transfer"/>
                    <input type="hidden" name="attachable_id" value="{{ $transfer->id }}"/>
                    <input type="file" name="file" class="text-sm text-ink-body file:mr-2 file:py-1 file:px-3 file:rounded file:border file:border-surface-border file:bg-surface-tile file:text-xs"/>
                    <x-button type="submit" variant="ghost" class="text-xs shrink-0">Upload</x-button>
                </form>
            </x-bento-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Transfer Info</p>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-ink-muted text-xs">Transfer #</dt><dd class="font-mono font-medium text-primary-700">{{ $transfer->transfer_number }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">From</dt><dd class="font-medium text-ink-heading">{{ $transfer->fromDept->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">To</dt><dd class="font-medium text-ink-heading">{{ $transfer->toDept->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Purpose</dt><dd class="text-ink-body">{{ $transfer->purpose }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Requested By</dt><dd class="text-ink-body">{{ $transfer->requestedBy->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Date</dt><dd class="text-ink-body">{{ $transfer->created_at->format('M d, Y') }}</dd></div>
                    @if($transfer->notes)
                        <div><dt class="text-ink-muted text-xs">Notes</dt><dd class="text-ink-body">{{ $transfer->notes }}</dd></div>
                    @endif
                </dl>
            </x-bento-card>

            @if($transfer->headApprovedBy)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Head Approval</p>
                    <dl class="space-y-1.5 text-sm">
                        <div><dt class="text-ink-muted text-xs">Approved By</dt><dd class="text-ink-body">{{ $transfer->headApprovedBy->name }}</dd></div>
                        <div><dt class="text-ink-muted text-xs">Date</dt><dd class="text-ink-body">{{ $transfer->head_approved_at?->format('M d, Y H:i') }}</dd></div>
                    </dl>
                </x-bento-card>
            @endif

            @if($transfer->acknowledgedBy)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Acknowledged</p>
                    <dl class="space-y-1.5 text-sm">
                        <div><dt class="text-ink-muted text-xs">By</dt><dd class="text-ink-body">{{ $transfer->acknowledgedBy->name }}</dd></div>
                        <div><dt class="text-ink-muted text-xs">Date</dt><dd class="text-ink-body">{{ $transfer->acknowledged_at?->format('M d, Y H:i') }}</dd></div>
                    </dl>
                </x-bento-card>
            @endif
        </div>
    </div>
</x-app-layout>
