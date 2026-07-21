@props(['code', 'title', 'description'])
<div class="command-grid relative flex min-h-screen items-center justify-center overflow-hidden bg-[#090d14] p-5 text-slate-100">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(220,38,38,.15),transparent_32%),radial-gradient(circle_at_80%_85%,rgba(249,115,22,.08),transparent_30%)]"></div>
    <section class="relative w-full max-w-xl rounded-2xl border border-white/10 bg-[#161d29]/95 p-7 text-center shadow-2xl shadow-black/40 sm:p-10" role="alert">
        <p class="text-xs font-bold uppercase tracking-[.24em] text-orange-400">Fire Command Center · {{ $code }}</p>
        <h1 class="mt-3 text-3xl font-black text-white">{{ $title }}</h1>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-400">{{ $description }}</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">@auth<x-ui.button :href="route('dashboard')">{{ __('common.dashboard') }}</x-ui.button>@endauth<x-ui.button variant="secondary" onclick="history.back()">{{ __('common.go_back') }}</x-ui.button></div>
    </section>
</div>
