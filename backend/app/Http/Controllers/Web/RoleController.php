<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\RolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);
        $roles = Role::withCount(['users', 'permissions'])->orderByDesc('is_platform')->get();
        $permissions = $this->permissions();

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);
        $role->load('permissions');
        $permissions = $this->permissions();

        return view('roles.show', compact('role', 'permissions'));
    }

    public function edit(Role $role): View
    {
        $this->guardSystemRole($role);
        $this->authorize('update', $role);
        $role->load('permissions');
        $permissions = $this->permissions();

        return view('roles.edit', compact('role', 'permissions'));
    }

    public function update(RolePermissionsRequest $request, Role $role, AuditService $audit): RedirectResponse
    {
        $this->guardSystemRole($role);
        DB::transaction(function () use ($request, $role, $audit): void {
            $old = $role->permissions()->pluck('permissions.name')->all();
            $role->permissions()->sync($request->validated('permissions', []));
            $audit->record('role.permissions_updated', $role, ['permissions' => $old], ['permissions' => $role->permissions()->pluck('permissions.name')->all()], null, $request->user());
        });

        return redirect()->route('roles.show', $role)->with('success', 'Permissions mises à jour.');
    }

    private function permissions()
    {
        return Permission::orderBy('name')->get()->groupBy(fn ($permission) => str($permission->name)->before('.')->toString());
    }

    private function guardSystemRole(Role $role): void
    {
        abort_if($role->name === 'system_admin', 403, 'The system administrator role is immutable.');
    }
}
