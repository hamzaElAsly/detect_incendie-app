<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Models\Camera; use App\Models\CameraHealthLog; use App\Models\Factory; use App\Services\FactoryContext; use Illuminate\Http\Request; use Illuminate\View\View;
class CameraHealthController extends Controller
{
 public function index(Request $r,FactoryContext $c):View{$f=$c->get();abort_unless($r->user()->hasPermission('cameras.view',$f),403);$logs=CameraHealthLog::forFactory($f)->with(['camera.zone'])->when($r->camera_id,fn($q,$v)=>$q->where('camera_id',$v))->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->date_from,fn($q,$v)=>$q->whereDate('recorded_at','>=',$v))->when($r->date_to,fn($q,$v)=>$q->whereDate('recorded_at','<=',$v))->latest('recorded_at')->paginate(25)->withQueryString();$cameras=$f->cameras()->orderBy('name')->get();return view('camera-health.index',compact('logs','cameras'));}
 public function show(Camera $camera,FactoryContext $c):View{$f=$c->get();abort_unless($camera->factory_id===$f->id&&request()->user()->hasPermission('cameras.view',$f),403);$logs=$camera->healthLogs()->latest('recorded_at')->paginate(50);$chart=$camera->healthLogs()->latest('recorded_at')->limit(30)->get()->reverse()->values();return view('camera-health.show',compact('camera','logs','chart'));}
}
