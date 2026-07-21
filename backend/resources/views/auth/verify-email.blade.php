@extends('layouts.auth')
@section('auth-content')

<h1 class="text-3xl font-black text-white">{{ __('auth.verify_title') }}</h1>
<p class="my-5 text-slate-400">{{ __('auth.verify_sent', ['email' => auth()->user()->email]) }}</p>

<form method="POST" action="{{ route('verification.send') }}">
  @csrf<x-ui.button type="submit">{{ __('auth.resend') }}</x-ui.button>
</form>

<form class="mt-4" method="POST" action="{{ route('logout') }}">
  @csrf
  <x-ui.button type="submit" variant="ghost">{{ __('common.logout') }}</x-ui.button>
</form>

@endsection
