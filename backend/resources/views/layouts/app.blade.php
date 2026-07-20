<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" class="h-full">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
 <script>!function(){const t=localStorage.getItem('fire-command-theme'),d=t?t==='dark':matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.classList.toggle('dark',d)}()</script>
 <title>{{ $title ?? __('common.dashboard') }} · Fire Command Center</title>@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-full">
<div class="min-h-screen lg:flex">
 <button data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm lg:hidden" aria-label="{{ __('common.close') }}"></button>
 <aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-white/8 bg-[#111827] text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="{{ __('navigation.main') }}">
  <div class="flex h-18 items-center justify-between border-b border-white/8 px-5">
   <a href="{{ route('dashboard') }}" class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-red-600 to-orange-500 shadow-lg shadow-red-950/30" aria-hidden="true">◆</span><span><strong class="block text-sm tracking-wide">FIRE COMMAND</strong><span class="block text-[10px] uppercase tracking-[.2em] text-slate-400">Control center</span></span></a>
   <button data-sidebar-toggle class="rounded-lg p-2 text-slate-400 hover:bg-white/8 hover:text-white lg:hidden" aria-label="{{ __('common.close') }}">✕</button>
  </div>
  <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
   <section><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">{{ __('navigation.command_center') }}</p>
    <a class="nav-link {{ request()->routeIs('dashboard')?'nav-link-active':'' }}" href="{{ route('dashboard') }}"><span aria-hidden="true">▦</span>{{ __('navigation.dashboard') }}</a>
    @can('cameras.view')<a class="nav-link {{ request()->routeIs('cameras.*')?'nav-link-active':'' }}" href="{{ route('cameras.index') }}"><span aria-hidden="true">◉</span>{{ __('navigation.cameras') }}</a>@endcan
    @can('events.view')<a class="nav-link {{ request()->routeIs('events.*')?'nav-link-active':'' }}" href="{{ route('events.index') }}"><span aria-hidden="true">▲</span>{{ __('navigation.events') }}</a>@endcan
    @can('alerts.view')<a class="nav-link {{ request()->routeIs('alerts.*')?'nav-link-active':'' }}" href="{{ route('alerts.index') }}"><span aria-hidden="true">!</span>{{ __('navigation.alerts') }}</a>@endcan
    @can('notifications.view')<a class="nav-link {{ request()->routeIs('notifications.*')?'nav-link-active':'' }}" href="{{ route('notifications.index') }}"><span aria-hidden="true">●</span>{{ __('navigation.notifications') }}</a>@endcan
   </section>
   <section><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">{{ __('navigation.infrastructure') }}</p>
    @can('zones.view')<a class="nav-link {{ request()->routeIs('zones.*')?'nav-link-active':'' }}" href="{{ route('zones.index') }}"><span aria-hidden="true">⌗</span>{{ __('navigation.zones') }}</a>@endcan
    @can('cameras.view')<a class="nav-link {{ request()->routeIs('camera-health.*')?'nav-link-active':'' }}" href="{{ route('camera-health.index') }}"><span aria-hidden="true">⌁</span>{{ __('navigation.camera_health') }}</a>@endcan
    @can('viewAny',App\Models\Factory::class)<a class="nav-link {{ request()->routeIs('factories.*')?'nav-link-active':'' }}" href="{{ route('factories.index') }}"><span aria-hidden="true">▤</span>{{ __('navigation.factories') }}</a>@endcan
   </section>
   @canany(['users.view','roles.view'])<section><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">{{ __('navigation.team_access') }}</p>
    @can('users.view')<a class="nav-link {{ request()->routeIs('members.*')?'nav-link-active':'' }}" href="{{ route('members.index') }}">{{ __('navigation.members') }}</a>@endcan
    @can('viewAny',App\Models\User::class)<a class="nav-link {{ request()->routeIs('users.*')?'nav-link-active':'' }}" href="{{ route('users.index') }}">{{ __('navigation.users') }}</a>@endcan
    @can('roles.view')<a class="nav-link {{ request()->routeIs('roles.*')?'nav-link-active':'' }}" href="{{ route('roles.index') }}">{{ __('navigation.roles') }}</a>@endcan
   </section>@endcanany
   <section><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">{{ __('navigation.operations') }}</p>
    @can('recipients.view')<a class="nav-link {{ request()->routeIs('recipients.*')?'nav-link-active':'' }}" href="{{ route('recipients.index') }}">{{ __('navigation.recipients') }}</a>@endcan
    @can('reports.view')<a class="nav-link {{ request()->routeIs('reports.*')?'nav-link-active':'' }}" href="{{ route('reports.index') }}">{{ __('navigation.reports') }}</a>@endcan
    @can('audit_logs.view')<a class="nav-link {{ request()->routeIs('audit-logs.*')?'nav-link-active':'' }}" href="{{ route('audit-logs.index') }}">{{ __('navigation.audit_logs') }}</a>@endcan
    @can('api_clients.view')<a class="nav-link {{ request()->routeIs('api-clients.*')?'nav-link-active':'' }}" href="{{ route('api-clients.index') }}">{{ __('navigation.api_clients') }}</a>@endcan
   </section>
   @canany(['plans.view','subscriptions.view','settings.view'])<section><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">{{ __('navigation.platform') }}</p>
    @can('plans.view')<a class="nav-link {{ request()->routeIs('plans.*')?'nav-link-active':'' }}" href="{{ route('plans.index') }}">{{ __('navigation.plans') }}</a>@endcan
    @can('subscriptions.view')<a class="nav-link {{ request()->routeIs('subscriptions.*')?'nav-link-active':'' }}" href="{{ route('subscriptions.index') }}">{{ __('navigation.subscriptions') }}</a>@endcan
    @can('settings.view')<a class="nav-link {{ request()->routeIs('settings.*')?'nav-link-active':'' }}" href="{{ route('settings.edit') }}">{{ __('navigation.settings') }}</a>@endcan
   </section>@endcanany
  </nav>
  <div class="border-t border-white/8 p-4"><a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-white/6"><span class="grid size-9 place-items-center rounded-lg bg-slate-700 text-sm font-bold">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ auth()->user()->name }}</strong><span class="block truncate text-xs text-slate-400">{{ auth()->user()->roles()->first()?->label ?? __('common.user') }}</span></span></a></div>
 </aside>
 <div class="min-w-0 flex-1">
  <header class="sticky top-0 z-30 flex h-18 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur-xl dark:border-[#273244] dark:bg-[#090d14]/90 sm:px-6 lg:px-8">
   <button data-sidebar-toggle class="btn-ghost px-3 lg:hidden" aria-label="{{ __('navigation.open_menu') }}">☰</button>
   <div class="min-w-0 flex-1"><p class="truncate text-[10px] font-bold uppercase tracking-[.18em] text-red-600">{{ $currentFactory?->name ?? __('navigation.platform') }}</p><h1 class="truncate text-base font-bold">{{ $title ?? __('navigation.dashboard') }}</h1></div>
   @if($availableFactories->isNotEmpty())<div class="relative hidden sm:block"><button data-dropdown-toggle aria-controls="factory-menu" aria-expanded="false" class="btn-secondary max-w-48 truncate">⌂ {{ $currentFactory?->name ?? __('common.factory') }} ▾</button><div data-dropdown-menu id="factory-menu" class="absolute right-0 top-12 hidden w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-[#273244] dark:bg-[#161d29]">@foreach($availableFactories as $factory)<form method="POST" action="{{ route('factory.switch',$factory) }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-white/8">{{ $factory->name }}</button></form>@endforeach</div></div>@endif
   <a href="{{ route('locale.switch',app()->getLocale()==='fr'?'en':'fr') }}" class="btn-ghost px-3" aria-label="{{ __('common.language') }}">{{ strtoupper(app()->getLocale()) }}</a>
   <button data-theme-toggle class="btn-ghost px-3" aria-label="{{ __('common.theme') }}"><span data-theme-icon aria-hidden="true">☾</span></button>
   @can('notifications.view')<a href="{{ route('notifications.index') }}" class="relative btn-ghost px-3" aria-label="{{ __('navigation.notifications') }}">●@if($unreadNotificationsCount)<span class="absolute right-0 top-0 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] text-white">{{ $unreadNotificationsCount }}</span>@endif</a>@endcan
   <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost px-3" aria-label="{{ __('common.logout') }}">↪</button></form>
  </header>
  <main class="mx-auto w-full max-w-[1920px] p-4 sm:p-6 lg:p-8"><x-alerts.flash />@yield('content')</main>
  <footer class="border-t border-slate-200 px-8 py-5 text-center text-xs text-slate-500 dark:border-[#273244]">© {{ date('Y') }} Fire Command Center · {{ __('common.industrial_protection') }}</footer>
 </div>
</div>
</body></html>
