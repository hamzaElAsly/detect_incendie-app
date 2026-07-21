<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $users = User::with(['roles', 'factories'])->when($request->q, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%$value%")->orWhere('email', 'like', "%$value%")))->when($request->status === 'active', fn ($query) => $query->where('is_active', true))->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))->latest()->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);
        $user->load(['roles.permissions', 'factories']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->guardCriticalDeactivation($request, $user);
        DB::transaction(function () use ($request, $user, $audit): void {
            $old = $user->only(['name', 'email', 'phone', 'is_active']);
            $user->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            $audit->record('user.updated', $user, $old, $user->fresh()->only(['name', 'email', 'phone', 'is_active']), null, $request->user());
        });

        return redirect()->route('users.show', $user)->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user, AuditService $audit): RedirectResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->factories()->wherePivot('role', 'factory_owner')->exists(), 422, 'Un propriétaire d’usine ne peut pas être supprimé directement.');
        if ($user->is_super_admin && User::where('is_super_admin', true)->where('is_active', true)->whereKeyNot($user->id)->doesntExist()) {
            throw ValidationException::withMessages(['user' => 'Le dernier super-administrateur actif ne peut pas être désactivé.']);
        }
        DB::transaction(function () use ($user, $audit): void {
            $user->update(['is_active' => false]);
            $audit->record('user.deactivated', $user, ['is_active' => true], ['is_active' => false], null, request()->user());
        });

        return back()->with('success', 'Utilisateur désactivé.');
    }

    private function guardCriticalDeactivation(UserRequest $request, User $user): void
    {
        if ($request->boolean('is_active')) {
            return;
        }
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['is_active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }
        if ($user->is_super_admin && User::where('is_super_admin', true)->where('is_active', true)->whereKeyNot($user->id)->doesntExist()) {
            throw ValidationException::withMessages(['is_active' => 'Le dernier super-administrateur actif ne peut pas être désactivé.']);
        }
    }
}
