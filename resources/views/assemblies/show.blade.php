<x-app-layout>
    <x-page-header :title="$assembly->assembly_number" subtitle="Assembly Record">
        <x-slot name="actions">
            <x-button href="{{ route('assemblies.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 space-y-4">

            {{-- Output --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Output</p>
                <div class="flex items-center gap-6 text-sm">
                    <div>
                        <span class="text-ink-muted text-xs block">Item Produced</span>
                        <span class="font-semibold text-ink-heading text-base">{{ $assembly->output_item_name }}</span>
                    </div>
                    <div>
                        <span class="text-ink-muted text-xs block">Qty Produced</span>
                        <span class="font-semibold text-primary-700">{{ $assembly->qty_produced }} {{ $assembly->output_unit }}</span>
                    </div>
                </div>
            </x-bento-card>

            {{-- Components --}}
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border">
                    <p class="text-sm font-medium text-ink-heading">Components Used</p>
                </div>
                <x-table :headers="['Item', 'Unit', 'Qty Used']">
                    @foreach($assembly->components as $comp)
                        <x-table.row>
                            <td class="px-6 py-3 text-sm font-medium text-ink-heading">{{ $comp->item_name_snapshot }}</td>
                            <td class="px-6 py-3 text-sm text-ink-body">{{ $comp->unit }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-rose-600">-{{ $comp->qty_used }}</td>
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-bento-card>

            @if($assembly->notes)
                <x-bento-card>
                    <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-2">Notes</p>
                    <p class="text-sm text-ink-body">{{ $assembly->notes }}</p>
                </x-bento-card>
            @endif

            {{-- Attachments --}}
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Attachments</p>
                @if($assembly->attachments->isEmpty())
                    <p class="text-xs text-ink-muted italic mb-3">No attachments.</p>
                @else
                    <ul class="space-y-1 mb-4">
                        @foreach($assembly->attachments as $att)
                            <li class="flex items-center justify-between text-sm">
                                <a href="{{ $att->url() }}" target="_blank" class="text-primary-600 hover:underline truncate">{{ $att->original_name }}</a>
                                <span class="text-xs text-ink-muted ml-4 shrink-0">{{ $att->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="flex gap-2 items-end">
                    @csrf
                    <input type="hidden" name="attachable_type" value="assembly"/>
                    <input type="hidden" name="attachable_id" value="{{ $assembly->id }}"/>
                    <input type="file" name="file" class="text-sm text-ink-body file:mr-2 file:py-1 file:px-3 file:rounded file:border file:border-surface-border file:bg-surface-tile file:text-xs"/>
                    <x-button type="submit" variant="ghost" class="text-xs shrink-0">Upload</x-button>
                </form>
            </x-bento-card>
        </div>

        <div class="space-y-4">
            <x-bento-card>
                <p class="text-xs text-ink-muted font-medium uppercase tracking-wide mb-3">Assembly Info</p>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-ink-muted text-xs">Assembly #</dt><dd class="font-mono font-medium text-primary-700">{{ $assembly->assembly_number }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Department</dt><dd class="font-medium text-ink-heading">{{ $assembly->department->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Assembled By</dt><dd class="text-ink-body">{{ $assembly->assembledBy->name }}</dd></div>
                    <div><dt class="text-ink-muted text-xs">Date</dt><dd class="text-ink-body">{{ $assembly->assembled_at?->format('M d, Y H:i') ?? $assembly->created_at->format('M d, Y') }}</dd></div>
                </dl>
            </x-bento-card>
        </div>
    </div>
</x-app-layout>
