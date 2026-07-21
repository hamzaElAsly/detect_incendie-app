<?php

namespace App\Http\Controllers\Web;

use App\Domain\Subscriptions\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\SubscriptionRequest;
use App\Models\Factory;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('subscriptions.view'), 403);
        $subscriptions = Subscription::with(['factory', 'plan'])->when($request->status, fn ($query, $value) => $query->where('status', $value))->when($request->factory_id, fn ($query, $value) => $query->where('factory_id', $value))->latest()->paginate(20)->withQueryString();
        $factories = Factory::orderBy('name')->get();

        return view('subscriptions.index', compact('subscriptions', 'factories'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasPermission('subscriptions.manage'), 403);

        return view('subscriptions.form', ['subscription' => new Subscription, 'factories' => Factory::orderBy('name')->get(), 'plans' => Plan::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(SubscriptionRequest $request, SubscriptionService $service): RedirectResponse
    {
        $subscription = $service->create($request->validated(), $request->user());

        return redirect()->route('subscriptions.show', $subscription)->with('success', 'Abonnement attribué.');
    }

    public function show(Subscription $subscription): View
    {
        abort_unless(request()->user()->hasPermission('subscriptions.view'), 403);
        $subscription->load(['factory', 'plan']);

        return view('subscriptions.show', compact('subscription'));
    }

    public function edit(Subscription $subscription): View
    {
        abort_unless(request()->user()->hasPermission('subscriptions.manage'), 403);

        return view('subscriptions.form', compact('subscription') + ['factories' => Factory::orderBy('name')->get(), 'plans' => Plan::orderBy('name')->get()]);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        $service->update($subscription, $request->validated(), $request->user());

        return redirect()->route('subscriptions.show', $subscription)->with('success', 'Abonnement mis à jour.');
    }
}
