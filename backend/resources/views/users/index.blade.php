@extends('layouts.app', ['title' => 'Users'])

@section('content')
<div class="flex items-center justify-between gap-4">
    <h2 class="text-2xl font-black">Local users</h2>
    @can('create', App\Models\User::class)<a class="btn-primary" href="{{ route('users.create') }}">{{ __('phase2.users.create') }}</a>@endcan
</div>
<form class="panel my-5 flex gap-3 p-4">
    <input class="field" name="q" value="{{ request('q') }}" placeholder="Name or email">
    <button class="btn-secondary">Search</button>
</form>
<div class="panel overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50"><tr><th class="p-4">User</th><th class="p-4">Roles</th><th class="p-4">Status</th></tr></thead>
        <tbody class="divide-y">
            @forelse($users as $user)
                <tr>
                    <td class="p-4"><a class="font-bold text-primary" href="{{ route('users.show', $user) }}">{{ $user->name }}</a><small class="block">{{ $user->email }}</small></td>
                    <td class="p-4">{{ $user->is_super_admin ? 'System Admin' : $user->roles->pluck('label')->join(', ') }}</td>
                    <td class="p-4"><x-badges.status :color="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badges.status></td>
                </tr>
            @empty
                <tr><td colspan="3"><x-ui.empty-state title="No users" description="No local user matches this search." icon="U" /></td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t p-4">{{ $users->links() }}</div>
</div>
@endsection
