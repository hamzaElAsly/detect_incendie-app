@extends('layouts.auth')
@section('auth-content')
<p class="text-xs font-bold uppercase tracking-[.2em] text-orange-400">{{ __('auth.secure_area') }}</p>
<h1 class="mt-2 text-2xl font-black text-white">{{ __('auth.welcome') }}</h1>
<p class="mt-2 text-sm leading-6 text-slate-400">{{ __('auth.subtitle') }}</p>
<form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
 @csrf
 <x-forms.input name="email" :label="__('auth.email')" type="email" required autocomplete="email" autofocus class="border-white/10 bg-[#090d14] text-white" />
 <div><label for="password" class="mb-1.5 block text-sm font-semibold text-slate-200">{{ __('auth.password') }} <span class="text-red-500">*</span></label><div class="relative"><input id="password" name="password" type="password" required autocomplete="current-password" class="field border-white/10 bg-[#090d14] pr-12 text-white"><button type="button" data-password-toggle="password" class="btn-icon absolute right-1 top-1/2 -translate-y-1/2 text-slate-400 hover:bg-white/5 hover:text-white" aria-label="{{ __('auth.show_password') }}" aria-pressed="false">◉</button></div>@error('password')<p class="mt-1 text-sm text-red-400" role="alert">{{ $message }}</p>@enderror</div>
 <label class="flex items-center gap-3 text-sm text-slate-300"><input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-600 bg-[#090d14] text-red-600 focus:ring-red-500">{{ __('auth.remember') }}</label>
 <x-ui.button type="submit" class="w-full">{{ __('auth.login') }}</x-ui.button>
</form>
@endsection
