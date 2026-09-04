<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditService;
use App\Services\FactoryContext;

class FactorySettingsController
{
    public function __construct(private FactorySettingsService $settings, private AuditService $audit) {}

    public function show(FactoryContext $context)
    {
        $factory = $context->get();
        abort_unless(request()->user()->hasPermission('settings.view', $factory), 403);

        return response()->json(['success' => true, 'message' => 'Settings retrieved successfully', 'data' => $this->settings->all($factory), 'meta' => (object) []]);
    }

    public function update(FactorySettingsRequest $request, FactoryContext $context)
    {
        $factory = $context->get();
        $old = $this->settings->all($factory);
        $updated = $this->settings->update($factory, $request->validated());
        $this->audit->record('settings.updated', $factory, $old, $updated, $factory, $request->user(), $request);

        return response()->json(['success' => true, 'message' => 'Settings updated successfully', 'data' => $updated, 'meta' => (object) []]);
    }
}
