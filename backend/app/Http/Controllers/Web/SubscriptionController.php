<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\SubscriptionRequest; use App\Models\Factory; use App\Models\Plan; use App\Models\Subscription; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class SubscriptionController extends Controller
{
 public function index(Request $r):View{abort_unless($r->user()->hasPermission('subscriptions.view'),403);$subscriptions=Subscription::with(['factory','plan'])->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->factory_id,fn($q,$v)=>$q->where('factory_id',$v))->latest()->paginate(20)->withQueryString();$factories=Factory::orderBy('name')->get();return view('subscriptions.index',compact('subscriptions','factories'));}
 public function create():View{abort_unless(request()->user()->hasPermission('subscriptions.manage'),403);return view('subscriptions.form',['subscription'=>new Subscription,'factories'=>Factory::orderBy('name')->get(),'plans'=>Plan::where('is_active',true)->orderBy('name')->get()]);}
 public function store(SubscriptionRequest $r):RedirectResponse{$s=DB::transaction(fn()=>Subscription::create($r->validated()));return redirect()->route('subscriptions.show',$s)->with('success','Abonnement attribué.');}
 public function show(Subscription $subscription):View{abort_unless(request()->user()->hasPermission('subscriptions.view'),403);$subscription->load(['factory','plan']);return view('subscriptions.show',compact('subscription'));}
 public function edit(Subscription $subscription):View{abort_unless(request()->user()->hasPermission('subscriptions.manage'),403);return view('subscriptions.form',compact('subscription')+['factories'=>Factory::orderBy('name')->get(),'plans'=>Plan::orderBy('name')->get()]);}
 public function update(SubscriptionRequest $r,Subscription $subscription):RedirectResponse{DB::transaction(fn()=> $subscription->update($r->validated()));return redirect()->route('subscriptions.show',$subscription)->with('success','Abonnement mis à jour.');}
}
