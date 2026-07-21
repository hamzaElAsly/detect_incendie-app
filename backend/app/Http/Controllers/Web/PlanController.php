<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()->hasPermission('plans.view'), 403);
        $plans = Plan::withCount('subscriptions')->orderBy('name')->paginate(20);

        return view('plans.index', compact('plans'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasPermission('plans.manage'), 403);

        return view('plans.form', ['plan' => new Plan]);
    }

    public function store(PlanRequest $request, AuditService $audit): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request, $audit): Plan {
            $plan = Plan::create($request->data());
            $audit->record('plan.created', $plan, [], $plan->toArray(), null, $request->user());

            return $plan;
        });

        return redirect()->route('plans.edit', $plan)->with('success', 'Plan créé.');
    }

    public function edit(Plan $plan): View
    {
        abort_unless(request()->user()->hasPermission('plans.manage'), 403);

        return view('plans.form', compact('plan'));
    }

    public function update(PlanRequest $request, Plan $plan, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan, $audit): void {
            $old = $plan->toArray();
            $plan->update($request->data());
            $audit->record('plan.updated', $plan, $old, $plan->fresh()->toArray(), null, $request->user());
        });

        return back()->with('success', 'Plan mis à jour.');
    }

    public function toggle(Plan $plan, AuditService $audit): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('plans.manage'), 403);
        DB::transaction(function () use ($plan, $audit): void {
            $old = $plan->is_active;
            $plan->update(['is_active' => ! $old]);
            $audit->record('plan.status_changed', $plan, ['is_active' => $old], ['is_active' => ! $old], null, request()->user());
        });

        return back()->with('success', 'Statut du plan mis à jour.');
    }
}
