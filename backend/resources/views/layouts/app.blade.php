<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>!function(){const r=document.documentElement,t=localStorage.getItem('fire-command-theme'),d=t?t==='dark':matchMedia('(prefers-color-scheme:dark)').matches;r.classList.toggle('dark',d);r.classList.toggle('sidebar-collapsed',localStorage.getItem('fire-command-sidebar')==='collapsed')}()</script>
    <title>{{ $title ?? __('common.dashboard') }} · Fire Command Center</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
<button data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm lg:hidden" aria-label="{{ __('common.close') }}"></button>

<aside data-sidebar class="app-sidebar -translate-x-full lg:translate-x-0" aria-label="{{ __('navigation.main') }}">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" title="Fire Command Center">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-red-600 to-orange-500 shadow-lg shadow-red-950/30" aria-hidden="true">◆</span>
            <span data-sidebar-label class="min-w-0 whitespace-nowrap">
                <strong class="block text-sm tracking-wide">FIRE COMMAND</strong>
                <span class="block text-[10px] uppercase tracking-[.2em] text-slate-400">Control center</span>
            </span>
        </a>
        <button data-sidebar-toggle class="sidebar-icon-button lg:hidden" aria-label="{{ __('common.close') }}">✕</button>
    </div>

    <nav class="sidebar-nav">
        <section class="sidebar-group">
            <p data-sidebar-label class="sidebar-heading">{{ __('navigation.command_center') }}</p>
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" href="{{ route('dashboard') }}" title="{{ __('navigation.dashboard') }}"><span class="nav-icon" aria-hidden="true">▦</span><span data-sidebar-label>{{ __('navigation.dashboard') }}</span></a>
            @can('cameras.view')<a class="nav-link {{ request()->routeIs('cameras.*') ? 'nav-link-active' : '' }}" href="{{ route('cameras.index') }}" title="{{ __('navigation.cameras') }}"><span class="nav-icon" aria-hidden="true">◉</span><span data-sidebar-label>{{ __('navigation.cameras') }}</span></a>@endcan
            @can('events.view')<a class="nav-link {{ request()->routeIs('events.*') ? 'nav-link-active' : '' }}" href="{{ route('events.index') }}" title="{{ __('navigation.events') }}"><span class="nav-icon" aria-hidden="true">▲</span><span data-sidebar-label>{{ __('navigation.events') }}</span></a>@endcan
            @can('alerts.view')<a class="nav-link {{ request()->routeIs('alerts.*') ? 'nav-link-active' : '' }}" href="{{ route('alerts.index') }}" title="{{ __('navigation.alerts') }}"><span class="nav-icon" aria-hidden="true">!</span><span data-sidebar-label>{{ __('navigation.alerts') }}</span></a>@endcan
            @can('notifications.view')<a class="nav-link {{ request()->routeIs('notifications.*') ? 'nav-link-active' : '' }}" href="{{ route('notifications.index') }}" title="{{ __('navigation.notifications') }}"><span class="nav-icon" aria-hidden="true">●</span><span data-sidebar-label>{{ __('navigation.notifications') }}</span></a>@endcan
        </section>

        <section class="sidebar-group">
            <p data-sidebar-label class="sidebar-heading">{{ __('navigation.infrastructure') }}</p>
            @can('zones.view')<a class="nav-link {{ request()->routeIs('zones.*') ? 'nav-link-active' : '' }}" href="{{ route('zones.index') }}" title="{{ __('navigation.zones') }}"><span class="nav-icon" aria-hidden="true">⌗</span><span data-sidebar-label>{{ __('navigation.zones') }}</span></a>@endcan
            @can('cameras.view')<a class="nav-link {{ request()->routeIs('camera-health.*') ? 'nav-link-active' : '' }}" href="{{ route('camera-health.index') }}" title="{{ __('navigation.camera_health') }}"><span class="nav-icon" aria-hidden="true">⌁</span><span data-sidebar-label>{{ __('navigation.camera_health') }}</span></a>@endcan
            @can('viewAny', App\Models\Factory::class)<a class="nav-link {{ request()->routeIs('factories.*') ? 'nav-link-active' : '' }}" href="{{ route('factories.index') }}" title="{{ __('navigation.factories') }}"><span class="nav-icon" aria-hidden="true">▤</span><span data-sidebar-label>{{ __('navigation.factories') }}</span></a>@endcan
        </section>

        @canany(['users.view', 'roles.view'])
            <section class="sidebar-group">
                <p data-sidebar-label class="sidebar-heading">{{ __('navigation.team_access') }}</p>
                @can('users.view')<a class="nav-link {{ request()->routeIs('members.*') ? 'nav-link-active' : '' }}" href="{{ route('members.index') }}" title="{{ __('navigation.members') }}"><span class="nav-icon" aria-hidden="true">♟</span><span data-sidebar-label>{{ __('navigation.members') }}</span></a>@endcan
                @can('viewAny', App\Models\User::class)<a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}" href="{{ route('users.index') }}" title="{{ __('navigation.users') }}"><span class="nav-icon" aria-hidden="true">◎</span><span data-sidebar-label>{{ __('navigation.users') }}</span></a>@endcan
                @can('roles.view')<a class="nav-link {{ request()->routeIs('roles.*') ? 'nav-link-active' : '' }}" href="{{ route('roles.index') }}" title="{{ __('navigation.roles') }}"><span class="nav-icon" aria-hidden="true">◇</span><span data-sidebar-label>{{ __('navigation.roles') }}</span></a>@endcan
            </section>
        @endcanany

        <section class="sidebar-group">
            <p data-sidebar-label class="sidebar-heading">{{ __('navigation.operations') }}</p>
            @can('recipients.view')<a class="nav-link {{ request()->routeIs('recipients.*') ? 'nav-link-active' : '' }}" href="{{ route('recipients.index') }}" title="{{ __('navigation.recipients') }}"><span class="nav-icon" aria-hidden="true">✦</span><span data-sidebar-label>{{ __('navigation.recipients') }}</span></a>@endcan
            @can('reports.view')<a class="nav-link {{ request()->routeIs('reports.*') ? 'nav-link-active' : '' }}" href="{{ route('reports.index') }}" title="{{ __('navigation.reports') }}"><span class="nav-icon" aria-hidden="true">▥</span><span data-sidebar-label>{{ __('navigation.reports') }}</span></a>@endcan
            @can('audit_logs.view')<a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'nav-link-active' : '' }}" href="{{ route('audit-logs.index') }}" title="{{ __('navigation.audit_logs') }}"><span class="nav-icon" aria-hidden="true">≡</span><span data-sidebar-label>{{ __('navigation.audit_logs') }}</span></a>@endcan
            @can('api_clients.view')<a class="nav-link {{ request()->routeIs('api-clients.*') ? 'nav-link-active' : '' }}" href="{{ route('api-clients.index') }}" title="{{ __('navigation.api_clients') }}"><span class="nav-icon" aria-hidden="true">⌘</span><span data-sidebar-label>{{ __('navigation.api_clients') }}</span></a>@endcan
        </section>

        @canany(['plans.view', 'subscriptions.view', 'settings.view'])
            <section class="sidebar-group">
                <p data-sidebar-label class="sidebar-heading">{{ __('navigation.platform') }}</p>
                @can('plans.view')<a class="nav-link {{ request()->routeIs('plans.*') ? 'nav-link-active' : '' }}" href="{{ route('plans.index') }}" title="{{ __('navigation.plans') }}"><span class="nav-icon" aria-hidden="true">▧</span><span data-sidebar-label>{{ __('navigation.plans') }}</span></a>@endcan
                @can('subscriptions.view')<a class="nav-link {{ request()->routeIs('subscriptions.*') ? 'nav-link-active' : '' }}" href="{{ route('subscriptions.index') }}" title="{{ __('navigation.subscriptions') }}"><span class="nav-icon" aria-hidden="true">$</span><span data-sidebar-label>{{ __('navigation.subscriptions') }}</span></a>@endcan
                @can('settings.view')<a class="nav-link {{ request()->routeIs('settings.*') ? 'nav-link-active' : '' }}" href="{{ route('settings.edit') }}" title="{{ __('navigation.settings') }}"><span class="nav-icon" aria-hidden="true">⚙</span><span data-sidebar-label>{{ __('navigation.settings') }}</span></a>@endcan
            </section>
        @endcanany
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile.edit') }}" class="flex min-w-0 items-center gap-3 rounded-lg p-2 hover:bg-white/6" title="{{ auth()->user()->name }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-700 text-sm font-bold">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span data-sidebar-label class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ auth()->user()->name }}</strong><span class="block truncate text-xs text-slate-400">{{ auth()->user()->roles()->first()?->label ?? __('common.user') }}</span></span>
        </a>
    </div>
</aside>

<div class="app-shell">
    <header class="sticky top-0 z-30 flex h-18 items-center gap-2 border-b border-slate-200 bg-white/90 px-4 backdrop-blur-xl dark:border-[#273244] dark:bg-[#090d14]/90 sm:gap-3 sm:px-6 lg:px-8">
        <button data-sidebar-toggle class="btn-ghost px-3 lg:hidden" aria-label="{{ __('navigation.open_menu') }}">☰</button>
        <button data-sidebar-collapse class="btn-ghost hidden px-3 lg:inline-flex" aria-label="{{ __('navigation.collapse_menu') }}" aria-expanded="true">☰</button>
        <div class="min-w-0 flex-1"><p class="truncate text-[10px] font-bold uppercase tracking-[.18em] text-red-600">{{ $currentFactory?->name ?? __('navigation.platform') }}</p><h1 class="truncate text-base font-bold">{{ $title ?? __('navigation.dashboard') }}</h1></div>
        @if($availableFactories->isNotEmpty())<div class="relative hidden sm:block"><button data-dropdown-toggle aria-controls="factory-menu" aria-expanded="false" class="btn-secondary max-w-48 truncate">⌂ {{ $currentFactory?->name ?? __('common.factory') }} ▾</button><div data-dropdown-menu id="factory-menu" class="absolute right-0 top-12 hidden w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-[#273244] dark:bg-[#161d29]">@foreach($availableFactories as $factory)<form method="POST" action="{{ route('factory.switch', $factory) }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-white/8">{{ $factory->name }}</button></form>@endforeach</div></div>@endif
        <a href="{{ route('locale.switch', app()->getLocale() === 'fr' ? 'en' : 'fr') }}" class="btn-ghost px-3" aria-label="{{ __('common.language') }}">{{ strtoupper(app()->getLocale()) }}</a>
        <button data-theme-toggle class="btn-ghost px-3" aria-label="{{ __('common.theme') }}"><span data-theme-icon aria-hidden="true">☾</span></button>
        @can('notifications.view')<a href="{{ route('notifications.index') }}" class="relative btn-ghost px-3" aria-label="{{ __('navigation.notifications') }}">●@if($unreadNotificationsCount)<span class="absolute right-0 top-0 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] text-white">{{ $unreadNotificationsCount }}</span>@endif</a>@endcan
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost px-3" aria-label="{{ __('common.logout') }}">↪</button></form>
    </header>
    <main class="mx-auto w-full max-w-[1920px] p-4 sm:p-6 lg:p-8"><x-alerts.flash />@yield('content')</main>
    <footer class="border-t border-slate-200 px-4 py-5 text-center text-xs text-slate-500 dark:border-[#273244] sm:px-8">© {{ date('Y') }} Fire Command Center · {{ __('common.industrial_protection') }}</footer>
</div>
<dialog data-confirm-dialog class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/70 dark:border-[#273244] dark:bg-[#161d29] dark:text-slate-100"><div class="p-6"><h2 class="text-lg font-bold">{{ __('common.confirm_action') }}</h2><p data-confirm-message class="mt-2 text-sm text-slate-500"></p><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-ui.button variant="secondary" data-confirm-cancel>{{ __('common.cancel') }}</x-ui.button><x-ui.button variant="danger" data-confirm-accept>{{ __('common.confirm') }}</x-ui.button></div></div></dialog>
</body>
</html>
