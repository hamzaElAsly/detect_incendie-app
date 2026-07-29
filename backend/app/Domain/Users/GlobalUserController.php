<?php

namespace App\Domain\Users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GlobalUserController extends Controller
{
    public function create(): View
    {
        abort_unless(request()->user()->is_super_admin || request()->user()->hasRole('platform_admin'), 403);
        $roles = Role::where('is_platform', true)->when(! request()->user()->is_super_admin, fn ($q) => $q->where('name', '!=', 'super_admin'))->orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(StoreGlobalUserRequest $request, GlobalUserService $service): RedirectResponse
    {
        $user = $service->create($request->validated(), $request->assignableRole(), $request->user());

        return redirect()->route('users.show', $user)->with('success', __('phase2.users.created'));
    }
}
