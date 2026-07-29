<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\ZoneRequest;
use App\Models\Zone;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(Request $request, FactoryContext $context): View
    {
        $f = $context->get();
        abort_unless($request->user()->hasPermission('zones.view', $f), 403);
        $zones = Zone::forFactory($f)->withCount(['cameras', 'fireEvents'])->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('reference', 'like', "%$v%")))->when($request->risk_level, fn ($q, $v) => $q->where('risk_level', $v))->latest()->paginate(15)->withQueryString();

        return view('zones.index', compact('zones'));
    }

    public function create(FactoryContext $context): View
    {
        abort_unless(request()->user()->hasPermission('zones.create', $context->get()), 403);

        return view('zones.form', ['zone' => new Zone]);
    }

    public function store(ZoneRequest $request, FactoryContext $context): RedirectResponse
    {
        DB::transaction(fn () => $context->get()->zones()->create($request->validated()));

        return redirect()->route('zones.index')->with('success', 'Zone créée.');
    }

    public function edit(Zone $zone, FactoryContext $context): View
    {
        $this->guard($zone, $context, 'zones.update');

        return view('zones.form', compact('zone'));
    }

    public function update(ZoneRequest $request, Zone $zone, FactoryContext $context): RedirectResponse
    {
        $this->guard($zone, $context, 'zones.update');
        DB::transaction(fn () => $zone->update($request->validated()));

        return redirect()->route('zones.index')->with('success', 'Zone mise à jour.');
    }

    public function destroy(Zone $zone, FactoryContext $context): RedirectResponse
    {
        $this->guard($zone, $context, 'zones.delete');
        abort_if($zone->cameras()->exists(), 422, 'Une zone contenant des caméras ne peut pas être supprimée.');
        DB::transaction(fn () => $zone->delete());

        return back()->with('success', 'Zone supprimée.');
    }

    private function guard(Zone $zone, FactoryContext $context, string $permission): void
    {
        $f = $context->get();
        abort_unless($zone->factory_id === $f->id && request()->user()->hasPermission($permission,$f),403);
    }
}
