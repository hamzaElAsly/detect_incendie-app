<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\RecipientRequest;
use App\Models\AlertRecipient;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlertRecipientController extends Controller
{
    public function index(FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless(request()->user()->hasPermission('recipients.view', $factory), 403);
        $recipients = AlertRecipient::forFactory($factory)->with('user:id,name')->latest()->paginate(20);

        return view('recipients.index', compact('recipients'));
    }

    public function create(FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless(request()->user()->hasPermission('recipients.manage', $factory), 403);

        return view('recipients.form', ['recipient' => new AlertRecipient, 'members' => $factory->users()->orderBy('name')->get()]);
    }

    public function store(RecipientRequest $request, FactoryContext $context, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $context, $audit): void {
            $factory = $context->get();
            $recipient = $factory->alertRecipients()->create($request->validated());
            $audit->record('alert_recipient.created', $recipient, [], $recipient->toArray(), $factory, $request->user());
        });

        return redirect()->route('recipients.index')->with('success', 'Destinataire créé.');
    }

    public function edit(AlertRecipient $recipient, FactoryContext $context): View
    {
        $this->guard($recipient, $context);
        $members = $context->get()->users()->orderBy('name')->get();

        return view('recipients.form', compact('recipient', 'members'));
    }

    public function update(RecipientRequest $request, AlertRecipient $recipient, FactoryContext $context, AuditService $audit): RedirectResponse
    {
        $this->guard($recipient, $context);
        DB::transaction(function () use ($request, $recipient, $context, $audit): void {
            $old = $recipient->toArray();
            $recipient->update($request->validated());
            $audit->record('alert_recipient.updated', $recipient, $old, $recipient->fresh()->toArray(), $context->get(), $request->user());
        });

        return redirect()->route('recipients.index')->with('success', 'Destinataire mis à jour.');
    }

    public function destroy(AlertRecipient $recipient, FactoryContext $context, AuditService $audit): RedirectResponse
    {
        $this->guard($recipient, $context);
        DB::transaction(function () use ($recipient, $context, $audit): void {
            $audit->record('alert_recipient.deleted', $recipient, $recipient->toArray(), [], $context->get(), request()->user());
            $recipient->delete();
        });

        return back()->with('success', 'Destinataire supprimé.');
    }

    private function guard(AlertRecipient $recipient, FactoryContext $context): void
    {
        $factory = $context->get();
        abort_unless($recipient->factory_id === $factory->id && request()->user()->hasPermission('recipients.manage', $factory), 403);
    }
}
