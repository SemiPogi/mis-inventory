<x-app-layout>
    <x-page-header title="Users" subtitle="Manage system accounts and access levels.">
        <x-slot name="actions">
            <x-button href="{{ route('users.create') }}" variant="primary">
                <x-heroicon-o-user-plus class="w-4 h-4"/>
                New User
            </x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif
    @if(session('error'))
        <x-toast type="error" :message="session('error')"/>
    @endif

    <x-bento-card :padded="false">
        @if($users->isEmpty())
            <x-empty-state icon="users" title="No users yet" hint="Create the first user account."/>
        @else
            <x-table :headers="['Name', 'Email', 'Role', 'Status', '']">
                @foreach($users as $user)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">
                            {{ $user->name }}
                            @if($user->id === auth()->id())
                                <span class="ml-1.5 text-xs text-ink-muted">(you)</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-ink-body">{{ $user->email }}</td>
                        <td class="px-6 py-3">
                            @php
                                $roleColors = [
                                    'admin'      => 'bg-primary-50 text-primary-700',
                                    'staff'      => 'bg-emerald-50 text-emerald-700',
                                    'accounting' => 'bg-amber-50 text-amber-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center {{ $roleColors[$user->role] ?? 'bg-surface-page text-ink-body' }} text-xs font-medium px-2.5 py-1 rounded-full capitalize">
                                {{ $user->role }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            @if($user->is_active)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('users.edit', $user) }}"
                                   class="text-xs font-medium text-primary-600 hover:text-primary-700">Edit</a>

                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.deactivate', $user) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="text-xs font-medium {{ $user->is_active ? 'text-rose-500 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
