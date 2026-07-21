<?php

namespace App\Domain\Subscriptions;

use App\Domain\Audit\AuditService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $actor): Subscription
    {
        $this->ensureSingleCurrentSubscription($data['factory_id'], $data['status']);

        return DB::transaction(function () use ($data, $actor): Subscription {
            $subscription = new Subscription;
            $subscription->forceFill($data)->save();
            $this->audit->record('subscription.created', $subscription, [], $subscription->toArray(), $subscription->factory, $actor);

            return $subscription;
        });
    }

    public function update(Subscription $subscription, array $data, User $actor): void
    {
        $this->ensureSingleCurrentSubscription($data['factory_id'], $data['status'], $subscription);
        DB::transaction(function () use ($subscription, $data, $actor): void {
            $old = $subscription->toArray();
            $subscription->forceFill($data)->save();
            $this->audit->record('subscription.updated', $subscription, $old, $subscription->fresh()->toArray(), $subscription->factory, $actor);
        });
    }

    private function ensureSingleCurrentSubscription(string $factoryId, string $status, ?Subscription $except = null): void
    {
        if (! in_array($status, ['trial', 'active'], true)) {
            return;
        }
        $exists = Subscription::query()->where('factory_id', $factoryId)->whereIn('status', ['trial', 'active'])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['status' => __('phase2.subscriptions.current_exists')]);
        }
    }
}
