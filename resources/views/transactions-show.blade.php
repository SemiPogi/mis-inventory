<x-app-layout>
    <div class="mb-4">
        <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Back to Transactions
        </a>
    </div>

    @php
        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;
        $isOwner = auth()->id() === $submitterId;
    @endphp

    <x-page-header :title="$transaction->item_name_snapshot"
                   :subtitle="'Transaction #' . $transaction->id">
        <x-slot:actions>
            {{-- Status badge --}}
            @if($transaction->isCancelled())
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                    Cancelled
                </span>
            @elseif($transaction->type === 'received')
                <x-status-badge status="received">IN — Received</x-status-badge>
            @elseif($transaction->acknowledgment_status === 'acknowledged')
                <x-status-badge status="acknowledged">OUT — Acknowledged</x-status-badge>
            @else
                <x-status-badge status="pending">OUT — Pending</x-status-badge>
            @endif

            {{-- Cancel: own pending submission only --}}
            @if($isOwner && $transaction->isPendingApproval())
                <form method="POST" action="{{ route('transactions.cancel', $transaction) }}"
                      onsubmit="return confirm('Cancel this submission? This cannot be undone.')">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white hover:bg-rose-50 text-rose-700 text-xs font-semibold px-3 py-2 transition">
                        <x-heroicon-o-x-circle class="w-4 h-4"/>
                        Cancel Submission
                    </button>
                </form>
            @endif

            {{-- Re-submit: own rejected submission only --}}
            @if($isOwner && $transaction->isRejected())
                <a href="{{ route('transactions.resubmit', $transaction) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold px-3 py-2 transition">
                    <x-heroicon-o-arrow-path class="w-4 h-4"/>
                    Re-submit
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Left: details (col-span-2) --}}
        <x-bento-card class="lg:col-span-2 space-y-6">
            <div>
                <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Item Details</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-surface-page rounded-lg p-3">
                        <p class="text-xs text-ink-muted mb-1">Item</p>
                        <p class="font-medium text-ink-heading">{{ $transaction->item_name_snapshot }}</p>
                    </div>
                    <div class="bg-surface-page rounded-lg p-3">
                        <p class="text-xs text-ink-muted mb-1">Quantity</p>
                        <p class="font-medium text-ink-heading">{{ $transaction->qty }} {{ $transaction->unit }}</p>
                    </div>
                </div>
            </div>

            @if($transaction->type === 'received')
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Receipt Details</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Received From</p><p class="font-medium text-ink-heading">{{ $transaction->received_from ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">RIS / IAR No.</p><p class="font-medium text-ink-heading">{{ $transaction->ris_iar_number ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Date Received</p><p class="font-medium text-ink-heading">{{ $transaction->date_received ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Received By</p><p class="font-medium text-ink-heading">{{ $transaction->receivedBy->name ?? '—' }}</p></div>
                    </div>
                </div>
            @endif

            @if($transaction->type === 'released')
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Release Details</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Released To</p><p class="font-medium text-ink-heading">{{ $transaction->receiver_name ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Designation</p><p class="font-medium text-ink-heading">{{ $transaction->receiver_designation ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Office</p><p class="font-medium text-ink-heading">{{ $transaction->released_to_office ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Date Released</p><p class="font-medium text-ink-heading">{{ $transaction->date_released ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Released By</p><p class="font-medium text-ink-heading">{{ $transaction->releasedBy->name ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Purpose</p><p class="font-medium text-ink-heading">{{ $transaction->purpose ?? '—' }}</p></div>
                    </div>
                </div>
            @endif

            @if($transaction->remarks)
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-2">Remarks</p>
                    <p class="text-sm text-ink-body bg-surface-page rounded-lg p-3">{{ $transaction->remarks }}</p>
                </div>
            @endif
        </x-bento-card>

        {{-- Right: acknowledgment --}}
        <x-bento-card class="space-y-4">

            {{-- Rejection notice (shown to the submitter only) --}}
            @if($isOwner && $transaction->isRejected())
                <div class="rounded-lg bg-rose-50 border border-rose-200 p-4">
                    <p class="text-xs font-semibold text-rose-700 uppercase tracking-wide mb-1">Rejected</p>
                    <p class="text-sm text-rose-900">{{ $transaction->head_rejection_notes ?? 'No reason provided.' }}</p>
                </div>
            @endif

            <p class="text-xs font-medium text-ink-muted uppercase tracking-wide">Acknowledgment</p>

            @if($transaction->type === 'received')
                <p class="text-sm text-ink-body">No acknowledgment required for receipt.</p>
            @elseif($transaction->acknowledgment_status === 'acknowledged')
                <div class="space-y-2">
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-700 mb-1">Acknowledged By</p>
                        <p class="font-medium text-emerald-900">{{ $transaction->acknowledged_by_name }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-700 mb-1">Date</p>
                        <p class="font-medium text-emerald-900">{{ $transaction->acknowledged_date }}</p>
                    </div>
                    @if($transaction->acknowledgment_remarks)
                        <div class="bg-emerald-50 rounded-lg p-3">
                            <p class="text-xs text-emerald-700 mb-1">Remarks</p>
                            <p class="font-medium text-emerald-900">{{ $transaction->acknowledgment_remarks }}</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-rose-50 rounded-lg p-4 text-sm text-rose-800">
                    Pending acknowledgment from {{ $transaction->receiver_name }}.
                    <a href="{{ route('acknowledge.index') }}" class="underline ml-1">Record now</a>
                </div>
            @endif
        </x-bento-card>
    </div>
</x-app-layout>
