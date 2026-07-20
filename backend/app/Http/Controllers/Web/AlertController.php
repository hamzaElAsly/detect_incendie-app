<?php
namespace App\Http\Controllers\Web;
use App\Domain\Audit\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\AlertAcknowledgeRequest;
use App\Models\Alert;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class AlertController extends Controller
{
    public function index(Request $r,FactoryContext $c): View { $f=$c->get(); abort_unless($r->user()->hasPermission('alerts.view',$f),403); $alerts=Alert::forFactory($f)->with('fireEvent.camera')->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->severity,fn($q,$v)=>$q->where('severity',$v))->latest()->paginate(20)->withQueryString(); return view('alerts.index',compact('alerts')); }
    public function show(Alert $alert,FactoryContext $c): View { $this->guard($alert,$c,'alerts.view'); $alert->load(['fireEvent.camera','fireEvent.zone','acknowledgedBy']); return view('alerts.show',compact('alert')); }
    public function acknowledge(AlertAcknowledgeRequest $r,Alert $alert,AuditService $audit): RedirectResponse { DB::transaction(function()use($r,$alert,$audit){$a=Alert::lockForUpdate()->findOrFail($alert->id); if($a->status->value!=='acknowledged'){$old=$a->status->value;$a->update(['status'=>'acknowledged','acknowledged_at'=>now(),'acknowledged_by'=>$r->user()->id]);$audit->record('alert.acknowledged',$a,['status'=>$old],['status'=>'acknowledged'],$a->factory,$r->user(),$r);}}); return back()->with('success','Alerte acquittée.'); }
    private function guard(Alert $a,FactoryContext $c,string $p): void {$f=$c->get();abort_unless($a->factory_id===$f->id&&request()->user()->hasPermission($p,$f),403);}
}
