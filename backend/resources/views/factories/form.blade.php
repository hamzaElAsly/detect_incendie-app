@extends('layouts.app', ['title' => 'Factory settings'])

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase text-red-600">Local installation</p>
        <h2 class="text-2xl font-black">Factory settings</h2>
        <p class="text-sm text-slate-500">Profile for the single on-premise site monitored by this installation.</p>
    </div>

    <form method="POST" action="{{ route('factory-settings.update') }}" class="panel grid gap-5 p-6 sm:grid-cols-2">
        @csrf
        @method('PUT')

        <input type="hidden" name="status" value="active">
        <input type="hidden" name="is_active" value="1">

        <x-forms.input name="name" label="Site name" :value="$factory->name" required />
        <x-forms.input name="legal_name" label="Legal company name" :value="$factory->legal_name" />
        <x-forms.input name="slug" label="Local slug" :value="$factory->slug" required />
        <x-forms.input name="reference" label="Site reference" :value="$factory->reference" />
        <x-forms.input name="installation_identifier" label="Installation identifier" :value="$factory->installation_identifier" />
        <x-forms.input name="timezone" label="Timezone" :value="$factory->timezone ?? 'Africa/Casablanca'" required />
        <x-forms.input name="email" label="Site email" type="email" :value="$factory->email" />
        <x-forms.input name="phone" label="Site phone" :value="$factory->phone" />
        <x-forms.input name="emergency_contact" label="Emergency contact" :value="$factory->emergency_contact" />
        <x-forms.input name="city" label="City" :value="$factory->city" />
        <x-forms.input name="country" label="Country" :value="$factory->country ?? 'Morocco'" />
        <x-forms.input name="latitude" label="Latitude" type="number" step="0.0000001" :value="$factory->latitude" />
        <x-forms.input name="longitude" label="Longitude" type="number" step="0.0000001" :value="$factory->longitude" />

        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-semibold">Address</label>
            <textarea class="field" name="address" rows="3">{{ old('address', $factory->address) }}</textarea>
        </div>

        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-semibold">Internal notes</label>
            <textarea class="field" name="internal_notes" rows="4">{{ old('internal_notes', $factory->internal_notes) }}</textarea>
        </div>

        <x-ui.button type="submit" class="sm:col-span-2">Save factory settings</x-ui.button>
    </form>
</div>
@endsection
