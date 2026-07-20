<?php
namespace App\Http\Controllers\Web;
use App\Domain\Settings\FactorySettingsService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\WebSettingsRequest;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class FactorySettingController extends Controller
{
 public function edit(FactoryContext $c,FactorySettingsService $s):View{$f=$c->get();abort_unless(request()->user()->hasPermission('settings.view',$f),403);$settings=$s->all($f);return view('settings.edit',compact('settings'));}
 public function update(WebSettingsRequest $r,FactoryContext $c,FactorySettingsService $s):RedirectResponse{$data=$r->validated();$data['notification_channels']=$data['notification_channels']??[];$s->update($c->get(),$data);return back()->with('success','Paramètres enregistrés.');}
}
