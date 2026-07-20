<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\RecipientRequest; use App\Models\AlertRecipient; use App\Services\FactoryContext; use Illuminate\Http\RedirectResponse; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class AlertRecipientController extends Controller
{
 public function index(FactoryContext $c):View{$f=$c->get();abort_unless(request()->user()->hasPermission('recipients.view',$f),403);$recipients=AlertRecipient::forFactory($f)->with('user:id,name')->latest()->paginate(20);return view('recipients.index',compact('recipients'));}
 public function create(FactoryContext $c):View{$f=$c->get();abort_unless(request()->user()->hasPermission('recipients.manage',$f),403);return view('recipients.form',['recipient'=>new AlertRecipient,'members'=>$f->users()->orderBy('name')->get()]);}
 public function store(RecipientRequest $r,FactoryContext $c):RedirectResponse{DB::transaction(fn()=> $c->get()->alertRecipients()->create($r->validated()));return redirect()->route('recipients.index')->with('success','Destinataire créé.');}
 public function edit(AlertRecipient $recipient,FactoryContext $c):View{$this->guard($recipient,$c);$members=$c->get()->users()->orderBy('name')->get();return view('recipients.form',compact('recipient','members'));}
 public function update(RecipientRequest $r,AlertRecipient $recipient,FactoryContext $c):RedirectResponse{$this->guard($recipient,$c);DB::transaction(fn()=> $recipient->update($r->validated()));return redirect()->route('recipients.index')->with('success','Destinataire mis à jour.');}
 public function destroy(AlertRecipient $recipient,FactoryContext $c):RedirectResponse{$this->guard($recipient,$c);DB::transaction(fn()=> $recipient->delete());return back()->with('success','Destinataire supprimé.');}
 private function guard(AlertRecipient $x,FactoryContext $c):void{$f=$c->get();abort_unless($x->factory_id===$f->id&&request()->user()->hasPermission('recipients.manage',$f),403);}
}
