<x-app-layout>
    <x-page-header :title="$ris->ris_number" :subtitle="'Status: ' . $ris->statusLabel()">
        <x-slot name="actions">
            <x-button href="{{ route('ris.index') }}" variant="ghost">← Back</x-button>
            <x-button href="{{ route('ris.print', $ris) }}" variant="ghost" target="_blank">
                <x-heroicon-o-printer class="w-4 h-4"/> Print
            </x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif
    @if(session('error'))
        <x-toast type="error" :message="session('error')"/>
    @endif

    <div class="grid grid-cols-3 gap-6">
        {{-- Main card --}}
        <div class="col-span-2 space-y-4">
            {{-- Status timeline --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-4">Status Timeline</p>
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    @foreach(['draft' => 'Draft', 'pending_head' => 'Head Approval', 'pending_supply' => 'Supply', 'issued' => 'Issued', 'completed' => 'Completed'] as $s => $label)
                        @php
                            $steps = ['draft','pending_head','pending_supply','issued','completed'];
                            $currentIndex = array_search($ris->status, $steps);
                            $stepIndex = array_search($s, $steps);
                            $done = $currentIndex !== false && $stepIndex <= $currentIndex && !$ris->isRejected();
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1.5 rounded-full font-medium
                                {{ $ris->status === $s ? 'bg-primary-600 text-white' : ($done ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500') }}">
                                {{ $label }}
                            </span>
                            @if(!$loop->last) <span class="text-ink-muted">→</span> @endif
                        </div>
                    @endforeach
                    @if($ris->isRejected())
                        <span class="px-3 py-1.5 rounded-full font-medium bg-rose-100 text-rose-700">Rejected</span>
                    @endif
                </div>
            </x-bento-card>

            {{-- Items table --}}
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border">
                    <p class="text-sm font-medium text-ink-heading">Requested Items</p>
                </div>
                <x-table :headers="['Stock No.', 'Item', 'Unit', 'Req Qty', 'Issued Qty', 'Remarks']">
                    @foreach($ris->items as $item)
                        <x-table.row>
                            <td class="px-6 py-3 text-sm text-ink-muted">{{ $item->stock_no ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-ink-heading">{{ $item->item_name }}</td>
                            <td class="px-6 py-3 text-sm text-ink-body">{{ $item->unit }}</td>
                            <td class="px-6 py-3 text-sm text-ink-body">{{ $item->requested_qty }}</td>
                            <td class="px-6 py-3 text-sm font-medium {{ $item->issued_qty !== null ? 'text-primary-700' : 'text-ink-muted' }}">
                                {{ $item->issued_qty ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-ink-muted">{{ $item->remarks ?? '—' }}</td>
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-bento-card>

            {{-- Notes --}}
            @if($ris->notes)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-2">Notes / Remarks</p>
                    <p class="text-sm text-ink-body">{{ $ris->notes }}</p>
                </x-bento-card>
            @endif

            {{-- Head approval actions --}}
            @if($ris->isPendingHead() && auth()->user()->is_head && auth()->user()->department_id === $ris->requesting_dept_id)
                <x-bento-card>
                    <p class="text-sm font-medium text-ink-heading mb-3">Dept Head Action</p>
                    <div class="flex gap-3 flex-wrap">
                        <form method="POST" action="{{ route('ris.head.approve', $ris) }}">
                            @csrf @method('PATCH')
                            <x-button type="submit" variant="primary">
                                <x-heroicon-o-check class="w-4 h-4"/> Approve
                            </x-button>
                        </form>
                        <div x-data="{ open: false }">
                            <x-button variant="ghost" @click="open = !open" type="button">
                                <x-heroicon-o-x-mark class="w-4 h-4"/> Reject
                            </x-button>
                            <div x-show="open" x-transition class="mt-3 space-y-2">
                                <form method="POST" action="{{ route('ris.head.reject', $ris) }}" class="space-y-2">
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

            {{-- Acknowledge action --}}
            @php
                $canAck = $ris->isIssued() && (
                    auth()->user()->isAdmin() ||
                    auth()->user()->department_id === $ris->requesting_dept_id
                );
            @endphp
            @if($canAck)
                <x-bento-card>
                    <p class="text-sm font-medium text-ink-heading mb-2">Acknowledge Receipt</p>
                    <p class="text-xs text-ink-muted mb-3">Confirm that you have received the issued items. They will be added to your inventory.</p>
                    <form method="POST" action="{{ route('ris.acknowledge', $ris) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary">
                            <x-heroicon-o-check-circle class="w-4 h-4"/> Acknowledge Receipt
                        </x-button>
                    </form>
                </x-bento-card>
            @endif
        </div>

        {{-- Sidebar: meta --}}
        <div class="space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Request Info</p>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-ink-muted text-xs">RIS Number</dt>
                        <dd class="font-mono font-medium text-primary-700">{{ $ris->ris_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Department</dt>
                        <dd class="font-medium text-ink-heading">{{ $ris->requestingDept->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Purpose</dt>
                        <dd class="text-ink-body">{{ $ris->purpose }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Requested By</dt>
                        <dd class="text-ink-body">{{ $ris->requestedBy->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted text-xs">Date Submitted</dt>
                        <dd class="text-ink-body">{{ $ris->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </x-bento-card>

            @if($ris->headApprovedBy)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Head Approval</p>
                    <dl class="space-y-1.5 text-sm">
                        <div>
                            <dt class="text-ink-muted text-xs">Approved By</dt>
                            <dd class="text-ink-body">{{ $ris->headApprovedBy->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted text-xs">Date</dt>
                            <dd class="text-ink-body">{{ $ris->head_approved_at?->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </x-bento-card>
            @endif

            @if($ris->issuedBy)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Issued By Supply</p>
                    <dl class="space-y-1.5 text-sm">
                        <div>
                            <dt class="text-ink-muted text-xs">Issued By</dt>
                            <dd class="text-ink-body">{{ $ris->issuedBy->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted text-xs">Date</dt>
                            <dd class="text-ink-body">{{ $ris->issued_at?->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </x-bento-card>
            @endif

            @if($ris->acknowledgedBy)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Acknowledged</p>
                    <dl class="space-y-1.5 text-sm">
                        <div>
                            <dt class="text-ink-muted text-xs">Acknowledged By</dt>
                            <dd class="text-ink-body">{{ $ris->acknowledgedBy->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted text-xs">Date</dt>
                            <dd class="text-ink-body">{{ $ris->acknowledged_at?->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </x-bento-card>
            @endif
        </div>
    </div>
</x-app-layout>
