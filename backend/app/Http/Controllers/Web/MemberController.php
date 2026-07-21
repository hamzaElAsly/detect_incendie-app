<?php

namespace App\Http\Controllers\Web;

use App\Domain\Members\MembershipService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\MemberRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request, FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless($request->user()->hasPermission('users.view', $factory), 403);
        $members = $factory->users()->with('roles')
            ->when($request->q, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%$value%")->orWhere('email', 'like', "%$value%")))
            ->when($request->status, fn ($query, $value) => $query->wherePivot('status', $value))
            ->paginate(20)->withQueryString();

        return view('members.index', compact('members'));
    }

    public function create(FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless(request()->user()->hasPermission('users.invite', $factory), 403);

        return view('members.form', ['member' => new User, 'roles' => $this->assignableRoles(request()->user())]);
    }

    public function store(MemberRequest $request, FactoryContext $context, MembershipService $service): RedirectResponse
    {
        $user = $service->invite($context->get(), $request->validated(), $request->user());
        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('members.index')->with('success', __('phase2.members.invited'));
    }

    public function edit(User $member, FactoryContext $context): View
    {
        $this->guard($member, $context, 'users.update');
        $member->setRelation('pivot', $member->factories()->whereKey($context->id())->firstOrFail()->pivot);

        return view('members.form', ['member' => $member, 'roles' => $this->assignableRoles(request()->user())]);
    }

    public function update(MemberRequest $request, User $member, FactoryContext $context, MembershipService $service): RedirectResponse
    {
        $this->guard($member, $context, 'users.update');
        $service->update($context->get(), $member, $request->validated(), $request->user(), $request->boolean('is_active'));

        return redirect()->route('members.index')->with('success', __('phase2.members.updated'));
    }

    public function destroy(User $member, FactoryContext $context, MembershipService $service): RedirectResponse
    {
        $this->guard($member, $context, 'users.remove');
        $service->remove($context->get(), $member, request()->user());

        return back()->with('success', __('phase2.members.removed'));
    }

    private function guard(User $member, FactoryContext $context, string $permission): void
    {
        $factory = $context->get();
        abort_unless(! $member->is_super_admin && $member->belongsToFactory($factory) && request()->user()->hasPermission($permission, $factory), 403);
    }

    private function assignableRoles(User $actor)
    {
        return Role::query()->where('is_platform', false)
            ->when(! $actor->is_super_admin && ! $actor->roles()->where('roles.is_platform', true)->exists(), fn ($query) => $query->where('name', '!=', 'factory_owner'))
            ->orderBy('label')->get();
    }
}
