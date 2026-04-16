<x-app-layout>
    <div class="mb-6">
        <a href="{{ route('transactions.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Transactions</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 max-w-2xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">{{ $transaction->item_name_snapshot }}</h1>
                <p class="text-sm text-gray-500 mt-1">Transaction #{{ $transaction->id }}</p>
            </div>
            <div>
                @if($transaction->type == 'received')
                    <span class="bg-blue-50 text-blue-700 text-sm font-medium px-3 py-1 rounded-full">IN — Received</span>
                @elseif($transaction->acknowledgment_status == 'acknowledged')
                    <span class="bg-green-50 text-green-700 text-sm font-medium px-3 py-1 rounded-full">OUT — Acknowledged</span>
                @else
                    <span class="bg-red-50 text-red-700 text-sm font-medium px-3 py-1 rounded-full">OUT — Pending</span>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Item Details</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Item</p>
                        <p class="font-medium text-gray-800">{{ $transaction->item_name_snapshot }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Quantity</p>
                        <p class="font-medium text-gray-800">{{ $transaction->qty }} {{ $transaction->unit }}</p>
                    </div>
                </div>
            </div>

            @if($transaction->type == 'received')
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Receipt Details</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Received From</p>
                        <p class="font-medium text-gray-800">{{ $transaction->received_from ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">RIS / IAR No.</p>
                        <p class="font-medium text-gray-800">{{ $transaction->ris_iar_number ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Date Received</p>
                        <p class="font-medium text-gray-800">{{ $transaction->date_received ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Received By</p>
                        <p class="font-medium text-gray-800">{{ $transaction->receivedBy->name ?? '—' }}</p>
                    </div>
                </div>
            </div>
            @endif

            @if($transaction->type == 'released')
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Release Details</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Released To</p>
                        <p class="font-medium text-gray-800">{{ $transaction->receiver_name ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Designation</p>
                        <p class="font-medium text-gray-800">{{ $transaction->receiver_designation ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Office</p>
                        <p class="font-medium text-gray-800">{{ $transaction->released_to_office ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Date Released</p>
                        <p class="font-medium text-gray-800">{{ $transaction->date_released ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Released By</p>
                        <p class="font-medium text-gray-800">{{ $transaction->releasedBy->name ?? '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Purpose</p>
                        <p class="font-medium text-gray-800">{{ $transaction->purpose ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Acknowledgment</p>
                @if($transaction->acknowledgment_status == 'acknowledged')
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-green-50 rounded-lg p-3">
                        <p class="text-xs text-green-500 mb-1">Acknowledged By</p>
                        <p class="font-medium text-green-800">{{ $transaction->acknowledged_by_name }}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                        <p class="text-xs text-green-500 mb-1">Date</p>
                        <p class="font-medium text-green-800">{{ $transaction->acknowledged_date }}</p>
                    </div>
                    @if($transaction->acknowledgment_remarks)
                    <div class="bg-green-50 rounded-lg p-3 col-span-2">
                        <p class="text-xs text-green-500 mb-1">Remarks</p>
                        <p class="font-medium text-green-800">{{ $transaction->acknowledgment_remarks }}</p>
                    </div>
                    @endif
                </div>
                @else
                <div class="bg-red-50 rounded-lg p-4 text-sm text-red-600">
                    Pending acknowledgment from {{ $transaction->receiver_name }}.
                    <a href="{{ route('acknowledge.index') }}" class="underline ml-1">Record now</a>
                </div>
                @endif
            </div>
            @endif

            @if($transaction->remarks)
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Remarks</p>
                <p class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">{{ $transaction->remarks }}</p>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>