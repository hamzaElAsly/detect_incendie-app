<?php

namespace App\Http\Controllers\Web\Services;

use App\Domain\Audit\AuditService;
use App\Models\FireEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventWorkflowService
{
    public function __construct(private readonly AuditService $audit) {}

    public function transition(FireEvent $event, string $target, User $user, Request $request): FireEvent
    {
        return DB::transaction(function () use ($event, $target, $user, $request) {
            $locked = FireEvent::query()->lockForUpdate()->findOrFail($event->id);
            $current = $locked->status->value;
            $allowed = ['confirmed' => ['detected'], 'false_alarm' => ['detected', 'confirmed'], 'resolved' => ['detected', 'confirmed']];
            if (! in_array($current, $allowed[$target] ?? [], true)) {
                throw ValidationException::withMessages(['event' => 'Transition impossible depuis le statut '.$current.'.']);
            } $old = $locked->only(['status', 'confirmed_at', 'resolved_at', 'false_alarm_at']);
            $data = ['status' => $target];
            if ($target === 'confirmed') {
                $data += ['confirmed_at' => now(), 'confirmed_by' => $user->id];
            } if ($target === 'resolved') {
                $data += ['resolved_at' => now(), 'resolved_by' => $user->id];
            } if ($target === 'false_alarm') {
                $data += ['false_alarm_at' => now()];
            } $locked->update($data);
            $this->audit->record('event.'.$target, $locked, $old, $locked->fresh()->only(array_keys($old)), $locked->factory, $user, $request);

            return $locked->fresh();
        });
    }
}
