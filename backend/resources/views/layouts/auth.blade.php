@extends('layouts.guest')
@section('content')
<div class="command-grid relative flex min-h-screen items-center justify-center overflow-hidden bg-[#090d14] p-5 text-slate-100">
 <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_15%,rgba(220,38,38,.17),transparent_30%),radial-gradient(circle_at_82%_85%,rgba(249,115,22,.10),transparent_28%)]"></div>
 <div class="absolute right-5 top-5 z-10 flex gap-2"><a href="{{ route('locale.switch',app()->getLocale()==='fr'?'en':'fr') }}" class="btn-ghost text-slate-300">{{ strtoupper(app()->getLocale()) }}</a><button data-theme-toggle class="btn-ghost text-slate-300" aria-label="{{ __('common.theme') }}"><span data-theme-icon>☾</span></button></div>
 <div class="relative z-10 w-full max-w-md">
  <div class="mb-7 flex justify-center"><div class="flex items-center gap-3"><span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-red-600 to-orange-500 shadow-xl shadow-red-950/50">◆</span><span><strong class="block tracking-wider">FIRE COMMAND</strong><span class="text-[10px] uppercase tracking-[.22em] text-slate-400">Industrial control center</span></span></div></div>
  <section class="rounded-2xl border border-white/10 bg-[#161d29]/95 p-7 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-9">@yield('auth-content')</section>
  <p class="mt-6 text-center text-xs text-slate-500">© {{ date('Y') }} · {{ __('common.industrial_protection') }}</p>
 </div>
</div>
@endsection
