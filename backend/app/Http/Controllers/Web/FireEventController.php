<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\EventActionRequest;
use App\Http\Controllers\Web\Services\EventWorkflowService;
use App\Models\FireEvent;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class FireEventController extends Controller
{
    public function index(Request $request,FactoryContext $context): View { $f=$context->get(); abort_unless($request->user()->hasPermission('events.view',$f),403); $events=FireEvent::forFactory($f)->with(['camera:id,name,reference','zone:id,name'])->when($request->q,fn($q,$v)=>$q->whereHas('camera',fn($c)=>$c->where('name','like',"%$v%")))->when($request->status,fn($q,$v)=>$q->where('status',$v))->when($request->severity,fn($q,$v)=>$q->where('severity',$v))->latest('detected_at')->paginate(20)->withQueryString(); return view('events.index',compact('events')); }
    public function show(FireEvent $event,FactoryContext $context): View { $this->guard($event,$context,'events.view'); $event->load(['camera','zone','detections','alerts','notifications']); return view('events.show',compact('event')); }
    public function confirm(EventActionRequest $r,FireEvent $event,EventWorkflowService $s): RedirectResponse { $s->transition($event,'confirmed',$r->user(),$r); return back()->with('success','Événement confirmé.'); }
    public function falseAlarm(EventActionRequest $r,FireEvent $event,EventWorkflowService $s): RedirectResponse { $s->transition($event,'false_alarm',$r->user(),$r); return back()->with('success','Événement marqué comme faux positif.'); }
    public function resolve(EventActionRequest $r,FireEvent $event,EventWorkflowService $s): RedirectResponse { $s->transition($event,'resolved',$r->user(),$r); return back()->with('success','Événement résolu.'); }
    private function guard(FireEvent $e,FactoryContext $c,string $p): void { $f=$c->get(); abort_unless($e->factory_id===$f->id&&request()->user()->hasPermission($p,$f),403); }
}
