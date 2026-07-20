@extends('layouts.auth')
@section('auth-content')

<h2 class="text-3xl font-black">Vérifiez votre e-mail</h2>
<p class="my-5 text-slate-600">Un lien de vérification a été envoyé à {{ auth()->user()->email }}.</p>

<form method="POST" action="{{ route('verification.send') }}">
  @csrf<x-ui.button type="submit">Renvoyer le lien</x-ui.button>
</form>

<form class="mt-4" method="POST" action="{{ route('logout') }}">
  @csrf
  <button class="btn-ghost">Se déconnecter</button>
</form>

@endsection
