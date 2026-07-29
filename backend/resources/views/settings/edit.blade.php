@extends('layouts.app',['title'=>'Paramètres'])
@section('content')
<div class="mx-auto max-w-5xl"><h2 class="text-2xl font-black">Paramètres de l’usine</h2><form method="POST" action="{{ route('settings.update') }}" class="panel mt-6 grid gap-5 p-6 sm:grid-cols-2">@csrf @method('PUT')
<x-forms.input name="timezone" label="Fuseau horaire" :value="$settings['timezone']??$currentFactory->timezone" required/>
<x-forms.input name="retention_days" label="Conservation (jours)" type="number" :value="$settings['retention_days']??90" required/>
<x-forms.input name="default_fire_threshold" label="Seuil feu par défaut" type="number" step="0.01" :value="$settings['default_fire_threshold']??.5" required/>
<x-forms.input name="default_smoke_threshold" label="Seuil fumée par défaut" type="number" step="0.01" :value="$settings['default_smoke_threshold']??.5" required/>
<x-forms.input name="alert_cooldown" label="Cooldown alerte (secondes)" type="number" :value="$settings['alert_cooldown']??60" required/>
<x-forms.input name="required_positive_frames" label="Frames positives requises" type="number" :value="$settings['required_positive_frames']??3" required/>
<x-forms.input name="camera_offline_threshold_seconds" label="Seuil hors ligne caméra (secondes)" type="number" min="30" max="3600" :value="$settings['camera_offline_threshold_seconds']??config('camera_health.offline_threshold_seconds')" required/>
<fieldset class="sm:col-span-2"><legend class="mb-3 text-sm font-semibold">Canaux de notification</legend><div class="flex flex-wrap gap-5">@foreach(['email','sms','push','webhook'] as $channel)<label class="flex gap-2"><input type="checkbox" name="notification_channels[]" value="{{ $channel }}" @checked(in_array($channel,old('notification_channels',$settings['notification_channels']??[])))>{{ strtoupper($channel) }}</label>@endforeach</div></fieldset>
@can('settings.update')<x-ui.button type="submit" class="sm:col-span-2">Enregistrer</x-ui.button>@endcan</form></div>
@endsection
