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
        abort_unless(request()->user()->hasRole('system_admin'), 403);
        $roles = Role::where('name', 'system_admin')->orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(StoreGlobalUserRequest $request, GlobalUserService $service): RedirectResponse
    {
        $user = $service->create($request->validated(), $request->assignableRole(), $request->user());

        return redirect()->route('users.show', $user)->with('success', __('phase2.users.created'));
    }
}
