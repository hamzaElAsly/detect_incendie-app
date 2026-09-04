@extends('layouts.app', ['title' => $user->name])

@section('content')
<div class="panel mx-auto max-w-4xl p-6">
    <div class="flex justify-between">
        <div>
            <h2 class="text-2xl font-black">{{ $user->name }}</h2>
            <p>{{ $user->email }} {{ $user->phone ? '- '.$user->phone : '' }}</p>
        </div>
        @can('update', $user)<a class="btn-primary" href="{{ route('users.edit', $user) }}">Edit</a>@endcan
    </div>

    <h3 class="mt-8 font-bold">Local access</h3>
    <div class="mt-3 divide-y">
        @forelse($user->roles as $role)
            <div class="flex justify-between py-3">
                <span>{{ $currentFactory?->name ?? 'Local installation' }}</span>
                <x-badges.status color="blue">{{ $role->label }}</x-badges.status>
            </div>
        @empty
            <x-ui.empty-state title="No roles" description="This account has no assigned local role." icon="R" />
        @endforelse
    </div>
</div>
@endsection
