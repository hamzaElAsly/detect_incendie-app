@extends('layouts.app', ['title' => 'Roles and permissions'])

@section('content')
<h2 class="text-2xl font-black">Roles and permissions</h2>
<p class="mb-6 text-sm text-slate-500">Access control matrix for this on-premise installation.</p>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach($roles as $role)
        <a class="panel p-5 hover:border-primary/30" href="{{ route('roles.show', $role) }}">
            <div class="flex justify-between">
                <h3 class="font-bold">{{ $role->label }}</h3>
                @if($role->is_platform)<x-badges.status color="blue">System</x-badges.status>@endif
            </div>
            <p class="mt-4 text-sm">{{ $role->permissions_count }} permissions - {{ $role->users_count }} users</p>
        </a>
    @endforeach
</div>
<section class="panel mt-6 overflow-x-auto p-5">
    <h3 class="mb-4 font-bold">Summary matrix</h3>
    <table class="w-full text-xs">
        <thead><tr><th class="p-2 text-left">Domain</th>@foreach($roles as $role)<th class="p-2">{{ $role->label }}</th>@endforeach</tr></thead>
        <tbody class="divide-y">
            @foreach($permissions as $domain => $items)
                <tr>
                    <th class="p-2 text-left">{{ ucfirst($domain) }}</th>
                    @foreach($roles as $role)
                        <td class="p-2 text-center">{{ $role->permissions()->whereIn('permissions.id', $items->pluck('id'))->exists() ? 'yes' : '-' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endsection
