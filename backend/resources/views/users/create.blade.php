@extends('layouts.app',['title'=>__('phase2.users.create')])
@section('content')
<form class="panel mx-auto grid max-w-3xl gap-5 p-6 sm:grid-cols-2" method="POST" action="{{ route('users.store') }}">@csrf
<h2 class="text-xl font-black sm:col-span-2">{{ __('phase2.users.create') }}</h2>
<x-forms.input name="name" :label="__('phase2.users.name')" required/><x-forms.input name="email" :label="__('phase2.users.email')" type="email" required/>
<x-forms.input name="phone" :label="__('phase2.users.phone')"/><label><span class="mb-1.5 block text-sm font-semibold">{{ __('phase2.users.role') }}</span><select class="field" name="role" required>@foreach($roles as $role)<option value="{{ $role->name }}">{{ $role->label }}</option>@endforeach</select></label>
<x-forms.input name="password" :label="__('phase2.users.password')" type="password" required/><x-forms.input name="password_confirmation" :label="__('phase2.users.password_confirmation')" type="password" required/>
<label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="is_active" value="1" checked> {{ __('phase2.users.active') }}</label><x-ui.button type="submit" class="sm:col-span-2">{{ __('phase2.users.submit') }}</x-ui.button>
</form>
@endsection
