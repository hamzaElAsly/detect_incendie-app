<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\FactoryContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
class NotificationController extends Controller
{
 public function index(Request $r,FactoryContext $c):View{$f=$c->get();abort_unless($r->user()->hasPermission('notifications.view',$f),403);$notifications=Notification::forFactory($f)->with('fireEvent.camera')->when($r->channel,fn($q,$v)=>$q->where('channel',$v))->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest()->paginate(20)->withQueryString();return view('notifications.index',compact('notifications'));}
 public function show(Notification $notification,FactoryContext $c):View{$f=$c->get();abort_unless($notification->factory_id===$f->id&&request()->user()->hasPermission('notifications.view',$f),403);$notification->load(['fireEvent.camera','logs']);return view('notifications.show',compact('notification'));}
}
