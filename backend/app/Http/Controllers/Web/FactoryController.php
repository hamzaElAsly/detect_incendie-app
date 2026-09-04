<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\FactoryRequest;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FactoryController extends Controller
{
    public function editLocal(FactoryContext $context): View
    {
        $factory = $context->get();
        $this->authorize('update', $factory);

        return view('factories.form', ['factory' => $factory]);
    }

    public function updateLocal(FactoryRequest $request, FactoryContext $context, AuditService $audit): RedirectResponse
    {
        $factory = $context->get();
        $this->authorize('update', $factory);
        DB::transaction(function () use ($request, $factory, $audit): void {
            $old = $factory->toArray();
            $data = $request->validated();
            $data['is_active'] = true;
            $data['status'] = 'active';
            $factory->update($data);
            $audit->record('factory_profile.updated', $factory, $old, $factory->fresh()->toArray(), $factory, $request->user(), $request);
        });

        return back()->with('success', 'Factory settings saved.');
    }
}
