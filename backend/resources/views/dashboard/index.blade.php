@extends('layouts.app', ['title' => 'Command center'])

@section('content')
<x-ui.page-header
    :title="'Command center - '.$factory->name"
    description="Operational monitoring, camera availability, and incident response for this local site."
    eyebrow="Local installation"
>
    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 dark:border-[#273244] dark:bg-[#161d29] dark:text-slate-300">
        <span class="size-2 rounded-full bg-green-500"></span>Updated at {{ now()->format('H:i') }}
    </span>
</x-ui.page-header>

@if($criticalAlerts->isNotEmpty())
    <section class="critical-pulse mb-6 rounded-2xl border border-red-500/50 bg-red-600 p-5 text-white" role="alert">
        <div class="flex items-center gap-4">
            <span class="grid size-11 place-items-center rounded-xl bg-white/15 text-xl">!</span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-widest text-red-100">Active critical alert</p>
                <p class="truncate font-bold">{{ $criticalAlerts->first()->message }}</p>
            </div>
            <a href="{{ route('alerts.show', $criticalAlerts->first()) }}" class="btn bg-white text-red-700 hover:bg-red-50">Open</a>
        </div>
    </section>
@endif

<section aria-labelledby="live-title">
    <div class="mb-4 flex items-end justify-between">
        <div>
            <h2 id="live-title" class="text-lg font-bold">Live camera grid</h2>
            <p class="text-sm text-slate-500">Priority equipment streams and operational state.</p>
        </div>
        @can('cameras.view')<a class="btn-ghost" href="{{ route('cameras.index') }}">View cameras</a>@endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse($liveCameras as $camera)
            @php
                $latest = $camera->fireEvents->first();
                $active = $latest && in_array($latest->status->value, ['detected', 'confirmed'], true) && $latest->detected_at?->gt(now()->subHours(24));
                $warning = $active && ! in_array($latest->severity->value, ['high', 'critical'], true);
            @endphp
            <article class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-[#161d29] {{ $active ? ($warning ? 'border-orange-500' : 'critical-pulse border-red-500') : 'border-slate-200 dark:border-[#273244]' }} {{ $camera->status !== 'online' ? 'opacity-75' : '' }}">
                <div class="command-grid relative aspect-video bg-[#090d14]">
                    <div class="absolute inset-0 grid place-items-center text-center">
                        <div>
                            <span class="mx-auto grid size-12 place-items-center rounded-full border border-slate-700 bg-slate-800 text-slate-400">C</span>
                            <p class="mt-3 text-xs font-semibold text-slate-300">{{ $camera->status === 'online' ? 'Stream preview unavailable' : 'Camera disconnected' }}</p>
                        </div>
                    </div>
                    <div class="absolute left-3 top-3"><x-ui.health-indicator :status="$camera->status" /></div>
                    @if($active)
                        <div class="absolute right-3 top-3"><x-badges.status :color="$warning ? 'amber' : 'red'">{{ $latest->type->value }} - {{ round((float) $latest->confidence * 100) }}%</x-badges.status></div>
                    @endif
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-bold">{{ $camera->name }}</h3>
                            <p class="truncate text-xs text-slate-500">{{ $camera->zone?->name ?? 'No zone' }} - {{ $factory->name }}</p>
                        </div>
                        <span class="text-xs text-slate-500">{{ strtoupper($camera->stream_type) }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div><span class="block text-slate-500">Last signal</span><strong>{{ $camera->last_seen_at?->diffForHumans() ?? 'Never' }}</strong></div>
                        <div><span class="block text-slate-500">Last event</span><strong>{{ $latest?->detected_at?->diffForHumans() ?? 'None' }}</strong></div>
                    </div>
                    @can('cameras.update')<a href="{{ route('cameras.edit', $camera) }}" class="btn-ghost mt-3 w-full">Camera details</a>@endcan
                </div>
            </article>
        @empty
            <x-ui.empty-state class="panel col-span-full" title="No cameras configured" description="Add a camera to begin live monitoring." icon="C">
                @can('cameras.create')<a class="btn-primary" href="{{ route('cameras.create') }}">Add camera</a>@endcan
            </x-ui.empty-state>
        @endforelse
    </div>
</section>

<section class="mt-7" aria-labelledby="availability-title">
    <h2 id="availability-title" class="mb-4 text-lg font-bold">Camera availability</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        <x-cards.stat label="Total" :value="(int) ($summary['total_cameras'] ?? 0)" />
        <x-cards.stat label="Online" :value="(int) ($summary['online_cameras'] ?? 0)" tone="green" />
        <x-cards.stat label="Offline" :value="(int) ($summary['offline_cameras'] ?? 0)" />
        <x-cards.stat label="Active incidents" :value="(int) ($summary['unresolved_events'] ?? 0)" tone="red" />
        <x-cards.stat label="Availability" :value="round((float) ($metrics['camera_uptime'] ?? 0), 1).' %'" tone="green" />
        <x-cards.stat label="Average response" :value="round((float) ($summary['average_response_time'] ?? 0)).' s'" tone="amber" />
    </div>
</section>

<div class="mt-7 grid gap-6 xl:grid-cols-5">
    <section class="panel xl:col-span-3" aria-labelledby="zones-title">
        <header class="border-b border-slate-200 p-5 dark:border-[#273244]">
            <h2 id="zones-title" class="font-bold">Factory zones</h2>
            <p class="text-sm text-slate-500">Configured site areas and their current coverage.</p>
        </header>
        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($zones as $zone)
                @php
                    $risk = $zone->risk_level->value;
                    $tone = in_array($risk, ['high', 'critical'], true) ? 'red' : ($risk === 'medium' ? 'amber' : 'green');
                @endphp
                <article class="rounded-xl border border-slate-200 p-4 dark:border-[#273244]">
                    <div class="flex justify-between gap-2"><strong class="truncate">{{ $zone->name }}</strong><x-badges.status :color="$tone">{{ $risk }}</x-badges.status></div>
                    <p class="mt-1 text-xs text-slate-500">{{ $zone->floor ?? 'No sector' }}</p>
                    <div class="mt-4 flex gap-4 text-xs"><span><strong>{{ $zone->cameras_count }}</strong> cameras</span><span><strong>{{ $zone->fire_events_count }}</strong> events</span></div>
                </article>
            @empty
                <x-ui.empty-state class="col-span-full" title="No zones" description="No geographic data is configured." icon="Z" />
            @endforelse
        </div>
    </section>

    <section class="panel p-5 xl:col-span-2" aria-labelledby="stats-title">
        <h2 id="stats-title" class="font-bold">Event statistics - 7 days</h2>
        <p class="text-sm text-slate-500">Daily detection volume</p>
        @php($max = max(1, (int) $eventsChart->max('total')))
        <div class="mt-6 flex h-56 items-end gap-2" role="img" aria-label="Seven-day event histogram">
            @foreach($eventsChart as $point)
                <div class="flex h-full flex-1 flex-col justify-end gap-2">
                    <span class="text-center text-[10px] font-bold">{{ $point['total'] }}</span>
                    <div class="rounded-t bg-orange-500" style="height: {{ max(4, ($point['total'] / $max) * 100) }}%" title="{{ $point['total'] }} events"></div>
                    <span class="text-center text-[10px] text-slate-500">{{ \Carbon\Carbon::parse($point['day'])->format('d/m') }}</span>
                </div>
            @endforeach
            @if($eventsChart->isEmpty())<x-ui.empty-state class="m-auto" title="No events" />@endif
        </div>
    </section>
</div>

<div class="mt-7 grid gap-6 xl:grid-cols-5">
    <section class="panel overflow-hidden xl:col-span-3" aria-labelledby="recent-events-title">
        <header class="border-b border-slate-200 p-5 dark:border-[#273244]">
            <div class="flex items-center justify-between gap-3">
                <div><h2 id="recent-events-title" class="font-bold">Recent events</h2><p class="text-sm text-slate-500">Latest detections recorded on this site.</p></div>
                @can('events.view')<a class="btn-link" href="{{ route('events.index') }}">History</a>@endcan
            </div>
        </header>
        <div class="divide-y divide-slate-100 dark:divide-[#273244]">
            @forelse($recentEvents as $event)
                <a href="{{ route('events.show', $event) }}" class="flex flex-col gap-3 p-4 transition hover:bg-slate-50 dark:hover:bg-white/3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1"><strong class="block truncate">{{ $event->camera?->name ?? 'Unknown camera' }}</strong><span class="text-xs text-slate-500">{{ $event->zone?->name ?? 'No zone' }} - {{ $event->detected_at?->diffForHumans() }}</span></div>
                    <x-badges.status :color="in_array($event->severity->value, ['high', 'critical'], true) ? 'red' : 'amber'">{{ $event->severity->value }}</x-badges.status>
                    <x-badges.status :color="$event->status->value === 'resolved' ? 'green' : 'blue'">{{ $event->status->value }}</x-badges.status>
                </a>
            @empty
                <x-ui.empty-state title="No recent events" description="No recent detections have been recorded." icon="E" />
            @endforelse
        </div>
    </section>

    <section class="panel p-5 xl:col-span-2" aria-labelledby="health-title">
        <h2 id="health-title" class="font-bold">System health</h2>
        <p class="text-sm text-slate-500">Current equipment distribution.</p>
        <div class="mt-5 space-y-4">
            @forelse($cameraStatus as $status => $total)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-[#273244]"><x-ui.health-indicator :status="$status" /><strong>{{ $total }}</strong></div>
            @empty
                <x-ui.empty-state title="No telemetry" description="Camera health will appear after the first heartbeats arrive." icon="H" />
            @endforelse
        </div>
        @if($recentActivity->isNotEmpty())
            <div class="mt-6 border-t border-slate-200 pt-5 dark:border-[#273244]">
                <h3 class="text-sm font-bold">Recent activity</h3>
                <ul class="mt-3 space-y-2">@foreach($recentActivity->take(3) as $activity)<li class="truncate text-xs text-slate-500">{{ $activity->user?->name ?? 'System' }} - {{ $activity->action }}</li>@endforeach</ul>
            </div>
        @endif
    </section>
</div>
@endsection
