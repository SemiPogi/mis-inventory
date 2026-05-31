<x-app-layout>
    <x-page-header title="Receive Item" subtitle="Record items received from Supplies & Properties Office"/>

    @php
        $authUser = auth()->user();
        $isAutoApproved = $authUser->isAdmin() || $authUser->is_head;
        $pendingReceiveCount = $isAutoApproved
            ? \App\Models\Transaction::where('type', 'received')
                ->where('head_approval_status', 'pending')
                ->when(! $authUser->isAdmin(), fn($q) => $q->where('department_id', $authUser->department_id))
                ->count()
            : \App\Models\Transaction::where('type', 'received')
                ->where('head_approval_status', 'pending')
                ->where('received_by_user_id', $authUser->id)
                ->count();
    @endphp

    {{-- Staff: approval-required notice --}}
    @if(! $isAutoApproved)
        <div class="max-w-3xl mb-4 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 shrink-0 text-blue-500"/>
            <div>
                <p class="font-medium">Department head approval required</p>
                <p class="text-blue-700 mt-0.5">Your submission will be held pending until the department head approves it. Inventory is updated only after approval.</p>
                @if($pendingReceiveCount > 0)
                    <p class="mt-1 font-medium text-blue-900">
                        You have {{ $pendingReceiveCount }} receive {{ Str::plural('submission', $pendingReceiveCount) }} awaiting approval.
                        <a href="{{ route('transactions.index') }}" class="underline hover:text-blue-700">View in Transactions →</a>
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Head / Admin: pending from staff notice --}}
    @if($isAutoApproved && $pendingReceiveCount > 0)
        <div class="max-w-3xl mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-heroicon-o-clock class="w-5 h-5 mt-0.5 shrink-0 text-amber-500"/>
            <div>
                <p class="font-medium">{{ $pendingReceiveCount }} staff receive {{ Str::plural('submission', $pendingReceiveCount) }} awaiting your approval</p>
                <p class="mt-0.5 text-amber-700">
                    <a href="{{ route('approvals.index') }}" class="underline hover:text-amber-900 font-medium">Go to Approvals →</a>
                </p>
            </div>
        </div>
    @endif

    <x-bento-card class="max-w-3xl" x-data x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('receive.store') }}">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Item Details</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="name" required>Item Name</x-label>
                    <x-input id="name" name="name" :value="old('name', request('name'))" required/>
                </div>
                <div>
                    <x-label for="category">Category</x-label>
                    <x-select id="category" name="category">
                        <option value="">Select category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label for="brand">Brand</x-label>
                    <x-input id="brand" name="brand" :value="old('brand')"/>
                </div>
                <div>
                    <x-label for="model_number">Model Number</x-label>
                    <x-input id="model_number" name="model_number" :value="old('model_number')"/>
                </div>
                <div>
                    <x-label for="serial_number">Serial Number</x-label>
                    <x-input id="serial_number" name="serial_number" :value="old('serial_number')"/>
                </div>
                <div>
                    <x-label for="unit">Unit</x-label>
                    <x-input id="unit" name="unit" :value="old('unit', request('unit', 'pcs'))" placeholder="pcs / ream / box"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty', request('qty'))" required/>
                </div>
                <div>
                    <x-label for="expiry_date">Expiry Date</x-label>
                    <x-input id="expiry_date" name="expiry_date" type="date" :value="old('expiry_date')"/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Source &amp; Reference</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="received_from">Received From (S&amp;P Officer)</x-label>
                    <x-input id="received_from" name="received_from" :value="old('received_from', request('received_from'))"/>
                </div>
                <div>
                    <x-label for="ris_iar_number">RIS / IAR Number</x-label>
                    <x-input id="ris_iar_number" name="ris_iar_number" :value="old('ris_iar_number', request('ris_iar_number'))"/>
                </div>
                <div>
                    <x-label for="date_received" required>Date Received</x-label>
                    <x-input id="date_received" name="date_received" type="date" :value="old('date_received', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="received_by">Received By</x-label>
                    <x-input id="received_by" name="received_by" :value="old('received_by', auth()->user()->name)"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks', request('remarks')) }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                    Record Receipt
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>
        </form>
    </x-bento-card>
</x-app-layout>
