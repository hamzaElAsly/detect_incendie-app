<?php
namespace Tests\Unit;
use App\Domain\Settings\FactorySettingsService; use App\Models\Factory; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class FactorySettingsServiceTest extends TestCase { use RefreshDatabase; public function test_settings_are_upserted_per_factory():void{$factory=Factory::create(['name'=>'A','slug'=>'a']);$values=app(FactorySettingsService::class)->update($factory,['timezone'=>'Africa/Casablanca','retention_days'=>30]);$this->assertSame('Africa/Casablanca',$values['timezone']);$this->assertDatabaseHas('factory_settings',['factory_id'=>$factory->id,'key'=>'retention_days']);} }
