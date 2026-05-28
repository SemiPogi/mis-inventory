<x-app-layout>
    <x-page-header title="Acknowledge Receipt" subtitle="Record acknowledgment from receiving offices"/>

    {{-- Pending --}}
    <x-bento-card :padded="false" class="mb-6">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">
                Awaiting Acknowledgment <span class="text-ink-muted">({{ $pending->count() }})</span>
            </h2>
        </div>

        @if($pending->isEmpty())
            <x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments."/>
        @else
            <div class="divide-y divide-surface-border"
                 x-data="ackList()" x-init="$stagger($el)" data-anim="stagger">
                @foreach($pending as $tx)
                    <div id="tx-{{ $tx->id }}" class="px-6 py-4 transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-ink-heading">{{ $tx->item_name_snapshot }}</p>
                                <p class="text-xs text-ink-body mt-1">
                                    {{ $tx->qty }} {{ $tx->unit }} •
                                    Released to: <span class="text-ink-heading">{{ $tx->receiver_name }}{{ $tx->receiver_designation ? ' ('.$tx->receiver_designation.')' : '' }}</span>
                                    • {{ $tx->released_to_office }}
                                    • {{ $tx->date_released }}
                                </p>
                                @if($tx->purpose)
                                    <p class="text-xs text-ink-muted mt-1">Purpose: {{ $tx->purpose }}</p>
                                @endif
                            </div>
                            <x-button type="button" variant="primary"
                                @click="openModal({{ $tx->id }}, '{{ addslashes($tx->item_name_snapshot) }}', '{{ $tx->qty }} {{ $tx->unit }}', '{{ addslashes($tx->receiver_name) }}', '{{ addslashes($tx->released_to_office) }}')">
                                <x-heroicon-o-check class="w-4 h-4"/>
                                Acknowledge
                            </x-button>
                        </div>
                    </div>
                @endforeach

                {{-- Modal --}}
                <div x-show="modal.open" x-cloak x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 w-full max-w-md mx-4 animate-pop">
                        <h3 class="text-base font-semibold text-ink-heading mb-1">Record Acknowledgment</h3>
                        <p class="text-xs text-ink-muted mb-4">
                            <strong class="text-ink-heading" x-text="modal.item"></strong> • <span x-text="modal.qty"></span>
                            <br>Released to: <span x-text="modal.receiver"></span> • <span x-text="modal.office"></span>
                        </p>

                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <x-label for="ack-by" required>Acknowledged By</x-label>
                                    <x-input id="ack-by" name="acknowledged_by_name" required x-model="form.acknowledged_by_name"/>
                                </div>
                                <div>
                                    <x-label for="ack-date" required>Date Acknowledged</x-label>
                                    <x-input id="ack-date" name="acknowledged_date" type="date" required x-model="form.acknowledged_date"/>
                                </div>
                                <div>
                                    <x-label for="ack-remarks">Remarks</x-label>
                                    <x-textarea id="ack-remarks" name="acknowledgment_remarks" rows="2" x-model="form.acknowledgment_remarks" placeholder="e.g. Items received in good condition"/>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 mt-5">
                                <x-button type="button" variant="ghost" @click="modal.open = false">Cancel</x-button>
                                <x-button type="submit" variant="primary" x-bind:disabled="submitting" x-text="submitting ? 'Saving…' : 'Confirm'"></x-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </x-bento-card>

    {{-- Acknowledged (history) --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Acknowledged <span class="text-ink-muted">({{ $acknowledged->count() }})</span>
            </h2>
        </div>
        @if($acknowledged->isEmpty())
            <x-empty-state icon="document-text" title="No history yet" hint="Acknowledged transactions will appear here."/>
        @else
            <x-table :headers="['Item', 'Qty', 'Released To', 'Office', 'Acknowledged By', 'Date', 'Remarks']">
                @foreach($acknowledged as $tx)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->acknowledged_by_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->acknowledged_date }}</td>
                        <td class="px-6 py-3 text-ink-muted">{{ $tx->acknowledgment_remarks ?? '—' }}</td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>

    <script>
        function ackList() {
            return {
                modal: { open: false, id: null, item: '', qty: '', receiver: '', office: '' },
                form: {
                    acknowledged_by_name: '',
                    acknowledged_date: new Date().toISOString().slice(0, 10),
                    acknowledgment_remarks: '',
                },
                submitting: false,
                openModal(id, item, qty, receiver, office) {
                    this.modal = { open: true, id, item, qty, receiver, office };
                },
                async submit() {
                    if (this.submitting) return;
                    this.submitting = true;

                    const res = await fetch(`/acknowledge/${this.modal.id}`, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });

                    this.submitting = false;

                    if (!res.ok) {
                        alert('Could not record acknowledgment. Please try again.');
                        return;
                    }

                    const row = document.getElementById(`tx-${this.modal.id}`);
                    if (row) {
                        row.classList.add('animate-pop-out');
                        setTimeout(() => row.remove(), 250);
                    }
                    this.modal.open = false;
                    this.form.acknowledgment_remarks = '';
                },
            }
        }
    </script>
</x-app-layout>
