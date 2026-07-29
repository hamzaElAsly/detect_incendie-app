@extends('layouts.app',['title'=>'Nouveau client API'])
@section('content')
<form class="panel mx-auto max-w-3xl space-y-5 p-6" method="POST" action="{{ route('api-clients.store') }}">@csrf
<x-forms.input name="name" label="Nom du client" required/>
<div><label class="mb-1.5 block text-sm font-semibold">Adresses IP autorisées</label><textarea class="field" name="allowed_ips_text" rows="4">{{ old('allowed_ips_text') }}</textarea></div>
<fieldset><legend class="mb-2 font-semibold">Scopes</legend>@foreach(App\Models\ApiClient::SCOPES as $scope)<label class="mr-5 inline-flex gap-2"><input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope,old('scopes',['camera:heartbeat']),true))> {{ $scope }}</label>@endforeach</fieldset>
<x-forms.input name="expires_at" label="Expiration (optionnelle)" type="datetime-local" :value="old('expires_at')"/>
<x-ui.button type="submit">Créer et générer le secret</x-ui.button></form>
@endsection
