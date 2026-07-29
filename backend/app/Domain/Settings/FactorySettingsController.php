<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditService;
use App\Models\Factory;

class FactorySettingsController
{
    public function __construct(private FactorySettingsService $settings, private AuditService $audit) {}

    public function show(Factory $factory)
    {
        abort_unless(request()->user()->hasPermission('settings.view', $factory), 403);

        return response()->json(['success' => true, 'message' => 'Settings retrieved successfully', 'data' => $this->settings->all($factory), 'meta' => (object) []]);
    }

    public function update(FactorySettingsRequest $request, Factory $factory)
    {
        $old = $this->settings->all($factory);
        $updated = $this->settings->update($factory, $request->validated());
        $this->audit->record('settings.updated', $factory, $old, $updated, $factory, $request->user(), $request);

        return response()->json(['success' => true, 'message' => 'Settings updated successfully', 'data' => $updated, 'meta' => (object) []]);
    }
}
