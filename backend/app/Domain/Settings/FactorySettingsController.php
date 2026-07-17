<?php
namespace App\Domain\Settings;
use App\Models\Factory;
class FactorySettingsController { public function __construct(private FactorySettingsService $settings){} public function show(Factory $factory){return response()->json(['success'=>true,'message'=>'Settings retrieved successfully','data'=>$this->settings->all($factory),'meta'=>(object)[]]);} public function update(FactorySettingsRequest $request,Factory $factory){return response()->json(['success'=>true,'message'=>'Settings updated successfully','data'=>$this->settings->update($factory,$request->validated()),'meta'=>(object)[]]);} }
