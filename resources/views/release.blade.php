<x-app-layout>
    <x-page-header title="Release Item" subtitle="Release items to other offices"/>

    @php
        $authUser = auth()->user();
        $isAutoApproved = $authUser->isAdmin() || $authUser->is_head;
        $pendingReleaseCount = $isAutoApproved
            ? \App\Models\Transaction::where('type', 'released')
                ->where('head_approval_status', 'pending')
                ->when(! $authUser->isAdmin(), fn($q) => $q->where('department_id', $authUser->department_id))
                ->count()
            : \App\Models\Transaction::where('type', 'released')
                ->where('head_approval_status', 'pending')
                ->where('released_by_user_id', $authUser->id)
                ->count();
    @endphp

    {{-- Staff: approval-required notice --}}
    @if(! $isAutoApproved)
        <div class="max-w-3xl mb-4 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 shrink-0 text-blue-500"/>
            <div>
                <p class="font-medium">Department head approval required</p>
                <p class="text-blue-700 mt-0.5">Your release request will be held pending until the department head approves it. Stock is deducted only after approval.</p>
                @if($pendingReleaseCount > 0)
                    <p class="mt-1 font-medium text-blue-900">
                        You have {{ $pendingReleaseCount }} release {{ Str::plural('submission', $pendingReleaseCount) }} awaiting approval.
                        <a href="{{ route('transactions.index') }}" class="underline hover:text-blue-700">View in Transactions →</a>
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Head / Admin: pending from staff notice --}}
    @if($isAutoApproved && $pendingReleaseCount > 0)
        <div class="max-w-3xl mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-heroicon-o-clock class="w-5 h-5 mt-0.5 shrink-0 text-amber-500"/>
            <div>
                <p class="font-medium">{{ $pendingReleaseCount }} staff release {{ Str::plural('submission', $pendingReleaseCount) }} awaiting your approval</p>
                <p class="mt-0.5 text-amber-700">
                    <a href="{{ route('approvals.index') }}" class="underline hover:text-amber-900 font-medium">Go to Approvals →</a>
                </p>
            </div>
        </div>
    @endif

    <x-bento-card class="max-w-3xl" x-data="releaseForm()" x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('release.store') }}" @submit.prevent="onSubmit">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Select Item</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <x-label for="item_id" required>Item</x-label>
                    <x-select id="item_id" name="item_id" required x-model="itemId" @change="onItemChange($event)">
                        <option value="">— Select item in stock —</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                    data-qty="{{ $item->current_qty }}"
                                    data-unit="{{ $item->unit }}"
                                    @selected(old('item_id', request('item_id')) == $item->id)>
                                {{ $item->name }}{{ $item->brand ? ' — '.$item->brand : '' }}
                                ({{ $item->current_qty }} {{ $item->unit }} available)
                            </option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label>Available Qty</x-label>
                    <x-input readonly x-model="availableLabel" class="bg-surface-page text-ink-muted"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity to Release</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty', request('qty'))" required x-model.number="qty"/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Release To</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="released_to_office" required>Receiving Office</x-label>
                    <x-input id="released_to_office" name="released_to_office" :value="old('released_to_office', request('released_to_office'))" required placeholder="e.g. Nursing Unit 3"/>
                </div>
                <div>
                    <x-label for="receiver_name" required>Receiver Name</x-label>
                    <x-input id="receiver_name" name="receiver_name" :value="old('receiver_name', request('receiver_name'))" required/>
                </div>
                <div>
                    <x-label for="receiver_designation">Receiver Designation</x-label>
                    <x-input id="receiver_designation" name="receiver_designation" :value="old('receiver_designation', request('receiver_designation'))" placeholder="e.g. Head Nurse"/>
                </div>
                <div>
                    <x-label for="date_released" required>Date Released</x-label>
                    <x-input id="date_released" name="date_released" type="date" :value="old('date_released', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="released_by">Released By</x-label>
                    <x-input id="released_by" name="released_by" :value="old('released_by', auth()->user()->name)"/>
                </div>
                <div>
                    <x-label for="purpose">Purpose</x-label>
                    <x-input id="purpose" name="purpose" :value="old('purpose', request('purpose'))" placeholder="e.g. For printer replacement"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks', request('remarks')) }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4"/>
                    Release Item
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>

            {{-- Confirm modal --}}
            <div x-show="confirming"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 max-w-sm mx-4 animate-pop">
                    <h3 class="text-base font-semibold text-ink-heading mb-2">Large release</h3>
                    <p class="text-sm text-ink-body mb-5">
                        You're releasing <strong x-text="qty"></strong> out of <strong x-text="available"></strong> available
                        (<span x-text="Math.round((qty / available) * 100)"></span>%). Continue?
                    </p>
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="ghost" @click="confirming = false">Cancel</x-button>
                        <x-button type="button" variant="primary" @click="confirm()">Yes, release</x-button>
                    </div>
                </div>
            </div>
        </form>
    </x-bento-card>

    <script>
        function releaseForm() {
            return {
                itemId: '{{ old('item_id', request()->query('item_id', '')) }}',
                qty: {{ (int) old('qty', request()->query('qty', 0)) }},
                available: 0,
                unit: '',
                confirming: false,
                get availableLabel() {
                    return this.available ? `${this.available} ${this.unit}` : '';
                },
                init() {
                    if (this.itemId) this.refreshAvailable();
                },
                onItemChange(e) {
                    this.refreshAvailable(e.target);
                },
                refreshAvailable(selectEl) {
                    selectEl = selectEl || document.getElementById('item_id');
                    const opt = selectEl.options[selectEl.selectedIndex];
                    this.available = parseInt(opt?.dataset.qty || 0, 10);
                    this.unit = opt?.dataset.unit || '';
                },
                onSubmit(e) {
                    if (this.available > 0 && this.qty > this.available * 0.5 && !this.confirming) {
                        this.confirming = true;
                        return;
                    }
                    e.target.submit();
                },
                confirm() {
                    this.confirming = false;
                    document.querySelector('form').submit();
                },
            }
        }
    </script>
</x-app-layout>
