<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\FactoryRequest;
use App\Models\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FactoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Factory::class);
        $factories = Factory::withCount(['users', 'zones', 'cameras', 'fireEvents'])->with('currentSubscription.plan')
            ->when($request->q, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%$value%")->orWhere('reference', 'like', "%$value%")))
            ->when($request->status, fn ($query, $value) => $query->where('status', $value))->latest()->paginate(15)->withQueryString();

        return view('factories.index', compact('factories'));
    }

    public function create(): View
    {
        $this->authorize('create', Factory::class);

        return view('factories.form', ['factory' => new Factory]);
    }

    public function store(FactoryRequest $request, AuditService $audit): RedirectResponse
    {
        $factory = DB::transaction(function () use ($request, $audit): Factory {
            $factory = Factory::create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            $audit->record('factory.created', $factory, [], $factory->toArray(), $factory, $request->user());

            return $factory;
        });

        return redirect()->route('factories.show', $factory)->with('success', 'Usine créée.');
    }

    public function show(Factory $factory): View
    {
        $this->authorize('view', $factory);
        $factory->loadCount(['users', 'zones', 'cameras', 'fireEvents'])->load(['users' => fn ($query) => $query->limit(8), 'currentSubscription.plan']);
        $recentEvents = $factory->fireEvents()->with('camera')->latest('detected_at')->limit(6)->get();

        return view('factories.show', compact('factory', 'recentEvents'));
    }

    public function edit(Factory $factory): View
    {
        $this->authorize('update', $factory);

        return view('factories.form', compact('factory'));
    }

    public function update(FactoryRequest $request, Factory $factory, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $factory, $audit): void {
            $old = $factory->toArray();
            $factory->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            $audit->record('factory.updated', $factory, $old, $factory->fresh()->toArray(), $factory, $request->user());
        });

        return redirect()->route('factories.show', $factory)->with('success', 'Usine mise à jour.');
    }

    public function toggle(Factory $factory, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $factory);
        DB::transaction(function () use ($factory, $audit): void {
            $old = $factory->is_active;
            $factory->update(['is_active' => ! $old, 'status' => $old ? 'inactive' : 'active']);
            $audit->record('factory.status_changed', $factory, ['is_active' => $old], ['is_active' => ! $old], $factory, request()->user());
        });

        return back()->with('success', 'Statut de l’usine mis à jour.');
    }

    public function destroy(Factory $factory, AuditService $audit): RedirectResponse
    {
        $this->authorize('delete', $factory);
        DB::transaction(function () use ($factory, $audit): void {
            $audit->record('factory.deleted', $factory, $factory->toArray(), [], $factory, request()->user());
            $factory->delete();
        });

        return redirect()->route('factories.index')->with('success', 'Usine supprimée.');
    }
}
