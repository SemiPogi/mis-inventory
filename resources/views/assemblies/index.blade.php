<x-app-layout>
    <x-page-header title="Assemblies" subtitle="Items assembled from component parts.">
        <x-slot name="actions">
            @if(auth()->user()->canCreateVoucher())
                <x-button href="{{ route('assemblies.create') }}" variant="primary">
                    <x-heroicon-o-plus class="w-4 h-4"/> New Assembly
                </x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($assemblies->isEmpty())
            <x-empty-state icon="wrench-screwdriver" title="No assemblies yet" hint="Record an assembly to combine items into a new product."/>
        @else
            <x-table :headers="['Assembly #', 'Department', 'Output Item', 'Qty', 'Components', 'Date', '']">
                @foreach($assemblies as $asm)
                    <x-table.row>
                        <td class="px-6 py-3 font-mono text-sm font-medium text-primary-700">
                            <a href="{{ route('assemblies.show', $asm) }}" class="hover:underline">{{ $asm->assembly_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $asm->department->name }}</td>
                        <td class="px-6 py-3 text-sm font-medium text-ink-heading">{{ $asm->output_item_name }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $asm->qty_produced }} {{ $asm->output_unit }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $asm->components->count() }} parts</td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $asm->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('assemblies.show', $asm) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-3">{{ $assemblies->links() }}</div>
        @endif
    </x-bento-card>
</x-app-layout>
