<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\CameraRequest;
use App\Models\Camera;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class CameraController extends Controller
{
    public function index(Request $request,FactoryContext $context): View { $f=$context->get(); abort_unless($request->user()->hasPermission('cameras.view',$f),403); $cameras=Camera::forFactory($f)->with('zone:id,name')->when($request->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('reference','like',"%$v%")))->when($request->status,fn($q,$v)=>$q->where('status',$v))->latest()->paginate(15)->withQueryString(); return view('cameras.index',compact('cameras')); }
    public function create(FactoryContext $context): View { $f=$context->get(); abort_unless(request()->user()->hasPermission('cameras.create',$f),403); return view('cameras.form',['camera'=>new Camera,'zones'=>$f->zones()->orderBy('name')->get()]); }
    public function store(CameraRequest $request,FactoryContext $context): RedirectResponse { $data=$request->validated(); $data['is_active']=$request->boolean('is_active'); DB::transaction(fn()=> $context->get()->cameras()->create($data)); return redirect()->route('cameras.index')->with('success','Caméra créée.'); }
    public function edit(Camera $camera,FactoryContext $context): View { $this->guard($camera,$context,'cameras.update'); $zones=$context->get()->zones()->orderBy('name')->get(); return view('cameras.form',compact('camera','zones')); }
    public function update(CameraRequest $request,Camera $camera,FactoryContext $context): RedirectResponse { $this->guard($camera,$context,'cameras.update'); $data=$request->validated(); $data['is_active']=$request->boolean('is_active'); if(blank($data['stream_url']??null)) unset($data['stream_url']); DB::transaction(fn()=> $camera->update($data)); return redirect()->route('cameras.index')->with('success','Caméra mise à jour.'); }
    public function destroy(Camera $camera,FactoryContext $context): RedirectResponse { $this->guard($camera,$context,'cameras.delete'); abort_if($camera->fireEvents()->exists(),422,'Cette caméra possède des événements.'); DB::transaction(fn()=> $camera->delete()); return back()->with('success','Caméra supprimée.'); }
    private function guard(Camera $camera,FactoryContext $context,string $permission): void { $f=$context->get(); abort_unless($camera->factory_id===$f->id && request()->user()->hasPermission($permission,$f),403); }
}
