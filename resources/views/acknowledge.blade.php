<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Acknowledge Receipt</h1>
        <p class="text-sm text-gray-500 mt-1">Record acknowledgment from receiving offices</p>
    </div>

    {{-- Pending --}}
    <div class="bg-white rounded-xl border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Awaiting Acknowledgment ({{ $pending->count() }})</h2>
        </div>
        @if($pending->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">All releases have been acknowledged.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($pending as $tx)
            <div class="px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $tx->item_name_snapshot }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $tx->qty }} {{ $tx->unit }} &bull;
                            Released to: <span class="text-gray-700">{{ $tx->receiver_name }}{{ $tx->receiver_designation ? ' ('.$tx->receiver_designation.')' : '' }}</span> &bull;
                            {{ $tx->released_to_office }} &bull;
                            {{ $tx->date_released }}
                        </p>
                        @if($tx->purpose)
                            <p class="text-xs text-gray-400 mt-1">Purpose: {{ $tx->purpose }}</p>
                        @endif
                    </div>
                    <button onclick="openModal({{ $tx->id }}, '{{ $tx->item_name_snapshot }}', '{{ $tx->qty }} {{ $tx->unit }}', '{{ $tx->receiver_name }}', '{{ $tx->released_to_office }}')"
                        class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-4 py-2 rounded-lg">
                        Record Acknowledgment
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Acknowledged --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Acknowledged ({{ $acknowledged->count() }})</h2>
        </div>
        @if($acknowledged->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No acknowledged transactions yet.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Item</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Qty</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Released To</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Office</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Acknowledged By</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wide">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($acknowledged as $tx)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-800">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->acknowledged_by_name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ $tx->acknowledged_date }}</td>
                        <td class="px-6 py-3 text-gray-400">{{ $tx->acknowledgment_remarks ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Modal --}}
    <div id="ack-modal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl border border-gray-200 p-6 w-full max-w-md mx-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Record Acknowledgment</h3>
            <div id="modal-detail" class="text-xs text-gray-500 mb-4"></div>

            <form method="POST" id="ack-form" action="">
                @csrf
                @method('PATCH')

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Acknowledged By *</label>
                        <input type="text" name="acknowledged_by_name" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Date Acknowledged *</label>
                        <input type="date" name="acknowledged_date" value="{{ date('Y-m-d') }}" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">Remarks</label>
                        <textarea name="acknowledgment_remarks" rows="2"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. Items received in good condition"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        Confirm
                    </button>
                    <button type="button" onclick="closeModal()"
                        class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-5 py-2 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, item, qty, receiver, office) {
            document.getElementById('ack-form').action = '/acknowledge/' + id;
            document.getElementById('modal-detail').innerHTML =
                '<strong class="text-gray-700">' + item + '</strong> &bull; ' + qty +
                '<br>Released to: ' + receiver + ' &bull; ' + office;
            document.getElementById('ack-modal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('ack-modal').classList.add('hidden');
        }
    </script>
</x-app-layout>