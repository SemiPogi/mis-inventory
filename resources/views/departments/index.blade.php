<x-app-layout>
    <x-page-header title="Departments" subtitle="Manage hospital departments and their access.">
        <x-slot name="actions">
            <x-button href="{{ route('departments.create') }}" variant="primary">
                <x-heroicon-o-plus class="w-4 h-4"/>
                New Department
            </x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($departments->isEmpty())
            <x-empty-state icon="building-office" title="No departments yet" hint="Create the first department."/>
        @else
            <x-table :headers="['Name', 'Code', 'RC Code', 'Users', 'Type', 'Status', '']">
                @foreach($departments as $dept)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $dept->name }}</td>
                        <td class="px-6 py-3 font-mono text-sm text-primary-700">{{ $dept->code }}</td>
                        <td class="px-6 py-3 text-ink-muted text-sm">{{ $dept->responsibility_center_code ?? '—' }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $dept->users_count }}</td>
                        <td class="px-6 py-3">
                            @if($dept->is_supply_hub)
                                <span class="inline-flex items-center bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full">
                                    Supply Hub
                                </span>
                            @else
                                <span class="text-ink-muted text-sm">Department</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($dept->is_active)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('departments.edit', $dept) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Edit</a>
                                <form method="POST" action="{{ route('departments.toggle', $dept) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-xs font-medium {{ $dept->is_active ? 'text-rose-500 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
